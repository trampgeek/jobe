# JOBE

Version: 1.0 August 2014

Author: Richard Lobb, University of Canterbury, New Zealand.

## Introduction

Jobe (short for Job Engine) is a server that supports running of small
compile-and-run jobs in a variety of programming languages. It was
developed as a remote sandbox for use by
[CodeRunner](http://github.com/trampgeek/coderunner), 
a Moodle question-type plugin that asks students to write code to some
relatively simple specification. However, Jobe servers could be useful in
a variety of other contexts, particularly in education.

A job specifies a programming language, the source code, the standard input
to the run and an optional list of additional files. Jobe compiles the
source code (if compilation is appropriate in the specified language) and 
runs it with the given input data. It returns a run_result object containing
various status information plus the output and error output from the run.

The interface is via a RESTful API, that is documented [here](./restapi.pdf).

## Implementation status

The current version of Jobe (Version 1.0) implements
enough of the API to provide the services needed by CodeRunner. Only 
immediate-mode runs are supported, with run results being returned with the
response to the POST of the run requests. Run results are not retained by
the server (unless *run\_spec.debug* is true; see the API), so 
*get\_run\_status* always returns 404 not found.  C, Python3, Python2, Octave
and Java have been
tested at this stage, and untested code exists to support C++ and Matlab.

The Computer Science quiz server at the University of Canterbury switched to
exclusive use of the Jobe sandbox in early July 2014. At the time of writing
(mid August 2014) it has run several
tens of thousands of Python3 and C jobs unattended since then with only a few
minor bug fixes.

Sandboxing is fairly basic. It uses the [domjudge](http://domjudge.org) 
*runguard* program to run student jobs with restrictions on resource
allocation (memory, processes, cpu time) as a low-privileged user.
However it does not restrict any system calls and the task is not yet run
in a chroot jail.

Programs may write binary output but the results are returned to the caller
JSON-encoded, which requires UTF-8 strings. To avoid crashing the
json-encoder, the standard output and standard error output from the program
are taken as 8-bit character streams; characters below '\x20' (the space
character) and above '\x7E' are replaced by C-style hexadecimal encodings 
(e.g. '\x8E') except for newlines which are passed through directly, and
tabls and returns which are replaced with '\t' and '\r' respectively.
Also, the Runguard sandbox currently runs programs in the default C locale. 
As a consequence of these two constraints, programs that generate utf-8 output
cannot currently be run on Jobe. It is hoped to improve on this in the future.

Jobe is implemented using Ellis Lab's [codeigniter](http://codeigniter.com) plus the
[RESTserver plugin](https://github.com/philsturgeon/codeigniter-restserver) from
Phil Sturgeon. It uses Jaap Eldering's and Keith Johnson's *Runguard*
module from the programming contest server (DOMJudge)[http://domjudge.org] 
as a sandbox to limit resource use by submitted jobs.

## Installation

**WARNING** This current version is intended for installing on a
server that is firewalled to allow connections ONLY from authorised client
machines. If you install it on a machine without such firewalling,
anyone will be able to connect to your machine and run their own code
on it! **CAVEAT EMPTOR!**

Jobe runs only on Linux, which must have the Apache web server
installed and running. PHP must have been compiled with the System V
Semaphone and shared-memory functions enabled
(see here)[http://www.php.net/manual/en/sem.setup.php].
The Python3 and the C development system must also be
installed.

On Debian-based systems, a script to set up all the necessary web tools plus
all currently-supported languages is something like the following
(all commands as root):

    apt-get install php5 libapache2-mod-php5 php5-mcrypt mysql-server\
          libapache2-mod-auth-mysql php5-mysql php5-cli octave nodejs\
          git python3 build-essential openjdk-7-jre openjdk-7-jdk python3-pip
    pip3 install pylint

[pylint is strictly optional].

The first step is to clone the project in the web root directory WEBROOT
(usually /var/www on Debian-based systems or /var/www/html on Red Hat).
Do not clone the project elsewhere and attempt to add it to web root with
symbolic links. That breaks this installer. In what follows, replace
WEBROOT with either /var/www or /var/www/html as appropriate.

To clone the project:

    cd WEBROOT
    sudo git clone https://github.com/trampgeek/jobe.git

Installation is performed by the install script, which must be run as root
so that it can add the required jobe run users (jobe00, jobe01, etc) and
set-up a jobe-sudoers file in /etc/sudoers.d that allows the web server
to execute the runguard program as root and to kill any residual jobe
processes from the run.

    cd WEBROOT/jobe
    sudo ./install

To test the installation, first try running the tester with the command

    python3 testsubmit.py

All going well, you should then be able to copy the *testsubmit.py* file to
any client machine that is allowed to access the jobe server, edit the line

    JOBE_SERVER = 'localhost'

to reference the JOBE_SERVER, e.g. by replacing *localhost* with its IP
number, and re-run the tester with the same command from the client machine.

## Debugging

If you have problems installing Jobe, here are some things to check.

If the install script fails, check the error message. You should be able
    to read through the script and figure out what went wrong. Otherwise ...

1. Check the install went OK:

 1. Make sure your webserver has read access to the entire jobe subtree.
 1. Make sure your webserver has write access to jobe/files
 1. Make sure there exist users jobe and jobe00 through jobe09.
 1. Make sure there is a directory /home/jobe/runs owned by jobe and writeable
    by the webserver. [It should not be readable or writeable by all.]
 1. Make sure there is a directory /var/log/jobe.

If the install appears OK but testsubmit.py fails:

 1. It is running with Python3, right?
 1. Check the apache error log.
 1. Set DEBUGGING = True in testsubmit.py (around line 19). This will result
    in all jobe runs being saved in /home/jobe/runs. [Normally a run directory
    is removed after each run completes.]
 1. If something unexpected happened with the actual run of a program, find
    the run in /home/jobe/runs and try executing the program manually. [The
    run directory contains the source file, the bash command used to run it,
    plus the compile output and (if it ran) the stderr and stdout outputs.
 1. Check for any error messages in /var/log/jobe/*.
 1. Turn on debug level of logging in jobe/application/config/config.php by
    setting the log_threshold to 2 (around line 183). You should now get
    screeds of log info in the directory /var/log/jobe. Most of this comes
    from the framework; look for lines beginning *jobe*. These are all issued
    by restapi.php in application/controllers, which is the top level handler
    for all http requests.

If you still can't figure it out, email me (Richard Lobb; my gmail name is
trampgeek).

## Securing the site

As a minimum you should set up a firewall that prevents access from any
server other than the CodeRunner client (or whatever other client is using
your Jobe server). Using ufw (Uncomplicated Firewall) a possible command
sequence that will allow ssh access (port 22) from anywhere and web
access to jobe (assumed to be on port 80) from just your client is the
following:

    ufw allow 22/tcp
    ufw allow proto tcp to any port 80 from <your_client_ip>
    ufw enable

## Run_spec parameters

The Jobe REST API specification document defines the format of a so-called
*run_spec*, which is the record/object that is encoded within a POST request
or a run request to specify the job details. It includes the language_id, the
source code, the source file name, any standard input data, a list of required
files and a set of job parameters. The job parameters are not defined by the
REST API as they are implementation dependent. This section defines the
format of the *parameters* field of a *run_spec* in this implementation.

The allowable attributes of the parameters field, plus their global default values
in parentheses, are:

 1. disklimit (20): the maximum number of megabytes that can be written to disk file(s)
before the job is aborted
 1. streamsize (2): the maximum number of megabytes of standard output before the
job is aborted.
 1. cputime (5): the maximum number of seconds of CPU time before the job is aborted
 1. memorylimit (200): the maximum number of megabytes of memory the task can
consume. This value is used to set the Linux RLIMIT_STACK, RLIMIT_DATA and
RLIMIT_AS via the *setrlimit* system call. If the value is exceeded the job
is not aborted but malloc and/or mmap calls will fail to allocate more memory
with somewhat unpredictable results, although a segmentation fault is the most
likely outcome.
 1. numprocs (20): the maximum number of processes the task is allowed. If
this is exceeded the *fork* system call will fail with, again, somewhat
unpredictable outcomes.
 1. compileargs ([]): a list of string option values to pass to the compiler,
such as ["-Wall", "-std=c99"] for the C compiler. Meaningful only for compiled
languages.
 1. interpreterargs ([]): a list of string option values to pass to the 
language interpreter or Java VM etc when the program is executed. Meaningful
only for languages like Python, PHP and Java where the output from the compiler
is not pure executable machine code.
 1. runargs ([]): a list of string option values to pass to the executed
program, e.g. to set *argc* and *argv* for a C program. Not generally useful
from CodeRunner as there is no way to set parameters on a per-test-case basis.

Individual languages will usually set their own default values for *compileargs*
and *interpreterargs*. 

If any of the above attributes are defined within the run_spec
*parameters* field, the latter is used and the defaults are ignored.

The default values of *compileargs*
and *interpreterargs* for the currently-implemented languages are as follows.
An empty default means the global default is used.

<table>
<tr>
   <th>language_id</th><th>language</th><th>compileargs</th><th>interpreterargs</th>
</tr>
  <td>c</td><td>C</td><td>['-Wall', -Werror', '-std=c99', '-x c']</td><td></td>
<tr>
  <td>cpp</td><td>C++</td><td>['-Wall', '-Werror', '-x ++']</td><td></td>
</tr>
<tr>
  <td>python2</td><td>Python2</td><td></td><td>['-BESs']</td>
</tr>
<tr>
  <td>python3</td><td>Python3</td><td></td><td>['-BE']</td>
</tr>
<tr>
  <td>java</td><td>Java</td><td></td><td>['-Xrs', '-Xss8m', '-Xmx200m']</td>
</tr>
<tr>
  <td>nodejs</td><td>JavaScript (nodejs)</td><td></td><td>['--use_strict']</td>
</tr>
<tr>
  <td>octave</td><td>Octave (matlab variant)</td><td></td><td>['--norc', '--no-window-system', '--silent', '-H']</td>
</tr>
<tr>
  <td>php</td><td>PHP5</td><td></td><td>['--no-php-ini']</td>
</tr>

</table>


## Configuration

This version of jobe is configured for use by Moodle Coderunner. When using
Jobe from CodeRunner the 
various language compile and run options can be changed
via the sandbox Parameters field in the question authoring form (using the
advanced customisation capabilities) of either the question prototype
or within a particular question as suggested by the previous
section. For example, if the sandbox *Parameters* field is set to

        { 'compileargs': ['-Wall', '-Werror', 'std=c89'] }

for a C question, the code will be compiled with all warnings enabled, aborting
if any warnings are issued and will need to be C89 compliant.

If you wish to change the existing default options within Jobe, or you wish to
add new languages, you must edit the source code as follows.

The folder *application/libraries* contains all the code that executes
submitted jobs. The file *LanguageTask.php* defines an abstract class
*Task* that contains default configuration parameters for things like
memory limit, maximum cpu run time, maximum disk output, etc. For each
supported language, a subclass with a name of the form *&lt;Language&gt;_Task*
resides in a file named *&lt;language&gt;_task.php*. For example, *c_task.php*
contains all the parameters specific to running C tasks, *octave_task.php*
contains parameters for running Octave tasks, etc. To add a new language
to Jobe you just drop in a new *&lt;language&gt;_task.php* file;
its presence is autodetected
by the Restapi constructor and the language will be available immediately.

Each subclass of LanguageTask typically defines at least the following three
methods:

1. __construct(). This is the constructor. It should generally call the parent
   constructor then set any language-specific default compile and/or interpret
   and/or run options.

1. getVersion(). This returns a string defining what version of the language,
   compiler etc is supported. It's not actually used by CodeRunner but is
   available via the rest API.

1. compile(). Calling this method must result in the file named
   $this->sourceFileName being compiled, with an executable output file
   being placed in the current working directory. If compilation succeeds
   the name of the executable
   is usually be returned in $this->executableFileName, for use by
   the getRunCommand method. Interpreted languages might do nothing
   or might copy the program. If compilation fails,
   $this->cmpinfo should be set to an appropriate error message; any non-empty
   string is taken as a compile error. 

1. getRunCommand(). This method must return an array of strings that, when
   joined with a space separator, make a bash command to execute the
   program resulting from the compile(). Execution parameters
   like the Java heap size are set in this function. The output from this
   function is passed to the RunguardSandbox, after addition of standard
   I/O redirection plus other sandbox parameters (see *getParam* below).

Additionally the subclass may define:

1. filteredStderr(). This takes $this->stderr and returns a filtered version,
   which might be necessary in some languages to remove extraneous text
   or remove special characters like form-feed or tab in order to make the
   result more appropriate for subsequent use, e.g. for display to students
   in a CodeRunner result table.

1. filteredStdout(). This performs the same task as filteredStderr() except it
   filters stdout, available to the function as $this->stdout.


Good luck!

Richard

