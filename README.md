# JOBE

Version: 1.6.4, 22 January 2021


Author: Richard Lobb, University of Canterbury, New Zealand

Contributors: Tim Hunt, Fedor Lyanguzov, Kai-Cheung Leung

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

The languages C, C++, Python3, Python2,
Octave, Java, Pascal and PHP are all built-in. Other languages can be added
fairly easily although if using Jobe from CodeRunner it is usually even
easier to write a Python-based question type that scripts the execution of
the required language. See the
[CodeRunner documentation](http://coderunner.org.nz/mod/book/view.php?id=193&chapterid=749)
for an example.

The Computer Science quiz server at the University of Canterbury switched to
exclusive use of the Jobe sandbox in early July 2014. Since then
it has run many hundreds of thousands of Python3, C, Java and Octave jobs unattended
with only a few minor bug fixes and security refinements.

## Implementation status

The current version of Jobe (Version 1.6, January 2019) implements
a subset of the originally documented API, sufficient for use by CodeRunner.
It has been used for many years at the University of Canterbury for several
years, running many millions of submissions. Jobe is also used by over 600 other
CodeRunner sites around the world. It can be considered stable and secure,
though it should be run only on a separate appropriately-firewalled server.

With reference to the original API spec, onnly immediate-mode runs are
supported, with run results being returned with the
response to the POST of the run requests. Run results are not retained by
the server (unless *run\_spec.debug* is true; see the API), so
*get\_run\_status* always returns 404 not found.

File PUTs are supported but not POSTs. When used by CodeRunner, file IDs are
MD5 checksums of the file contents.

Since version 1.6, the Jobe server cleans the file cache whenever available
disk space drops below 5% of the disk size. It simply deletes all files that
haven't been used
for 2 days or more, so the server must have enough free disk space
to stay below 95% full for at least two whole days of running. For CodeRunner
clients this should not be a problem unless question authors enable large
classes of students to attach large files to their submissions. Support files
attached by question authors are unlikely to be a problem; a Jobe server
at the University of Canterbury
serving a large Moodle client with many thousands of questions accumulated only
200 MB of support files over several years.

For sandboxing, Jobe uses the [domjudge](http://domjudge.org)
*runguard* program to run student jobs with restrictions on resource
allocation (memory, processes, cpu time) as a low-privileged user.
However it does not restrict any system calls.

Programs may write binary output but the results are returned to the caller
JSON-encoded, which requires UTF-8 strings. To avoid crashing the
json-encoder, the standard output and standard error output from the program
are checked to see if they're valid utf-8. If so, they're returned unchanged.
Otherwise, they're taken as 8-bit character streams; characters below '\x20'
(the space
character) and above '\x7E' are replaced by C-style hexadecimal encodings
(e.g. '\x8E') except for newlines which are passed through directly, and
tabls and returns which are replaced with '\t' and '\r' respectively.

If Jobe is to correctly handle utf-8 output from programs, the Apache LANG
environment variable must be set to a UTF-8 compatible value. See
the section *Setting the locale* below.

Jobe is implemented using Ellis Lab's [codeigniter](http://codeigniter.com) plus the
[RESTserver plugin](https://github.com/chriskacerguis/codeigniter-restserver) originally
written by Phil Sturgeon and now maintained by Chris Kacerguis.

## Installation

**WARNING** Jobe is primarily intended for use on a
server that is firewalled to allow connections from authorised client
machines only. If you install it on a machine without such firewalling,
and do not control access with API keys (see later),
anyone will be able to connect to your machine and run their own code
on it! **CAVEAT EMPTOR!**

NOTE: a video walkthrough of the process of setting up a Jobe server
on a DigitalOcean droplet is [here](https://www.youtube.com/watch?v=dGpnQpLnERw).

Installation on Ubuntu 18.04 systems should be
straightforward but installation on other flavours of Linux or on systems
with non-standard configurations may require
Linux administrator skills.

An alternative approach, and probably the simplest way to get up and running,
is to use the [JobeInABox](https://hub.docker.com/r/trampgeek/jobeinabox/)
Docker image, which should be runnable with a single terminal command
on any Linux system that has
docker installed. Thanks to David Bowes for the initial work on this.
Please be aware that while this Docker image has been around for a couple of years
and no significant issues have been reported the developer has not himself
used it in a production environment. Feedback is welcomed. The steps to fire
up a Jobe Server on Digital Ocean using JobeInAbox are given below in section
*Setting up a JobeInAbox Digital Ocean server*.

Jobe runs only on Linux, which must have the Apache web server
installed and running. PHP must have been compiled with the System V
Semaphone and shared-memory functions enabled
(see here)[http://www.php.net/manual/en/sem.setup.php], but that's the norm.
Access Control Lists (ACLs) must be enabled; they normally are but if the
`/home/jobe` directory lands up on a mounted volume, you may need to
explicitly enable ACLs in the `mount` command or in `/etc/fstab`.
The Python3 and the C development system must also be
installed.

### Installing the necessary dependencies

On Ubuntu-16.04 or 18.04, the commands to set up all the necessary web tools plus
all currently-supported languages is the following:

    sudo apt-get install apache2 php libapache2-mod-php php-cli\
        php-mbstring nodejs git python3 build-essential default-jdk\
        python3-pip fp-compiler acl sudo sqlite3

    sudo apt-get install --no-install-suggests --no-install-recommends  octave

Octave and fp are required only if you need to run Octave or Pascal
programs respectively.

If you wish to use API-authentication, which is generally pointless when setting
up a private Jobe server, you also need the following:

    sudo apt install mysql-server php-mysql

Similar commands should work on other Debian-based Linux distributions,
although some differences are inevitable (e.g.: acl is preinstalled in Ubuntu,
whereas in debian it must be installed).

A Raspberry Pi user reports that they additionally had to use the command

    apt-get install --fix-missing

which may help with broken installs on other systems, too.

### Setting up pylint (if you want it)

Firstly, install pylint for your required version of python (assumed here to
be python3) with the command:

    sudo -H python3 -m pip install pylint

You also need to build the /etc/pylintrc file
to set the default options with one of the following commands, which must be
run as root (don't just try prefixing the command with sudo, as the output redirection
will fail).

Firstly try the command:

    pylint --reports=no --score=n --generate-rcfile > /etc/pylintrc

If that gives you an error "no such option: --score" (which happens with
older versions of pylint), try instead

    pylint --reports=no --generate-rcfile > /etc/pylintrc

### Installing Jobe

Clone the Jobe project in the web root directory WEBROOT
(usually /var/www/html).
Do not clone it elsewhere and attempt to add it to web root with
symbolic links. That breaks this installer. In what follows, replace
WEBROOT with either /var/www or /var/www/html as appropriate.

To clone Jobe:

    cd WEBROOT
    sudo git clone https://github.com/trampgeek/jobe.git

Installation is performed by the install script, which must be run as root
so that it can add the required jobe run users (jobe00, jobe01, etc) and
set-up a jobe-sudoers file in /etc/sudoers.d that allows the web server
to execute the runguard program as root and to kill any residual jobe
processes from the run.

    cd WEBROOT/jobe
    sudo ./install

On Centos6 systems (and possibly early Centos7 releases) you should also
comment out the line

    Defaults requiretty

in /etc/sudoers. This was
(reported as a bug)[https://bugzilla.redhat.com/show_bug.cgi?id=1196451]
and was fixed in later RHEL releases.

### Setting the locale

By default, Apache is configured to use the C locale. This means that programs
generating, say, UTF-8 output will fail with an error

    UnicodeEncodeError: 'ascii' codec can't encode character ...

If you wish to run UTF-8 code (recommended) you should
find the line in the Apache envvars file (on Ubuntu systems this is to be found
at /etc/apache2/envvars)

    export LANG=C

and change it to either C.UTF-8 (which changes the charset to UTF-8 but leaves
other locale settings unchanged) or to the required standard locale value, e.g.

    export LANG=en_NZ.UTF-8

Make sure that whatever locale you use is installed on the Jobe server.

Then restart apache with the command

    sudo service apache2 restart

Note:

1. The comment in the Apache envvars file suggesting the use of the default
locale probably won't
work, as this will also just give you ASCII text.

2. To take advantage of the UTF-8 capabilities in CodeRunner you will need
to use Version 3.3 or later.

## Setting up a JobeInAbox Digital Ocean server

For people wanting to get a Jobe server up in hurry, the following is
probably the simplest approach. This uses a minimal Digital Ocean virtual machine,
costing just $US5.00 per month, to run the Docker *JobeInAbox* image.
Other cloud servers, such as Amazon ECS, can of course also be used.

 1. Set yourself up with an account on [Digital Ocean](https://cloud.digitalocean.com).
 2. Create new Droplet: Ubuntu 20.04. x64, minimal config ($5 per month; 1GB CPI, 25GB disk)
 3. Connect to the server with an SSH client.
 4. Install docker (see https://phoenixnap.com/kb/how-to-install-docker-on-ubuntu-18-04): 
    sudo apt update; sudo apt install docker.io
 5. Launch JobeInABox with Docker: sudo docker run -d -p 80:80 --name jobe trampgeek/jobeinabox:latest

At this point you have a running Jobe server. You can check it's working by browsing to

    http://<hostname>/jobe/index.php/restapi/languages

You should get presented with a JSON list of installed languages.

And you can connect your CodeRunner plugin to it by setting the new JobeServer
IP number in the Admin panel of the plugin. You're in business!

All that remains is to firewall your new server so that only your Moodle server
can use it, and so it can't itself open outgoing connections. For example:

    sudo apt install ufw
    sudo ufw default reject outgoing
    sudo sudo ufw allow in 22/tcp
    sudo ufw allow in proto tcp to any port 80 from <your moodle server IP>
    sudo ufw enable


## Testing the install

To test the installation, first try running the tester with the command

    python3 testsubmit.py

The first time you run this command, the initial step of obtaining all the
different versions of all language is slow, as it has to test-drive all compilers and
interpreters. Be patient. Results are cached in a file
in /tmp so subsequent runs will be much faster, at least until the next reboot,
when the list is rebuilt.

All going well, you should then be able to copy the *testsubmit.py* file to
any client machine that is allowed to access the jobe server, edit the line

    JOBE_SERVER = 'localhost'

to reference the JOBE_SERVER, e.g. by replacing *localhost* with its IP
number, and re-run the tester with the same command from the client machine.

## Using Jobe

Usually Jobe is used as a server for Moodle CodeRunner questions. So once jobe
has been installed and tested with `testsubmit.py` it can be used by CodeRunner
questions by plugging the Jobe server hostname into the CodeRunner administrator
settings, replacing the default value of `jobe2.cosc.canterbury.ac.nz`.

However, Jobe can also be used standalone. The `testsubmit.py` program shows
how it can be invoked from a Python client. There are also two other simpler
clients provided in this repository: `simpletest.py` and `minimaltest.py`.
Note that the POST request
payload must a JSON object with a *run_spec* attribute as specified in the
document *restapi.pdf*. For example, the following POST data runs the classic
C "Hello World" program:

    {"run_spec": {"language_id": "c", "sourcefilename": "test.c", "sourcecode": "\n#include <stdio.h>\n\nint main() {\n    printf(\"Hello world\\n\");\n}\n"}}

The POST request must have the header

    Content-type: application/json; charset-utf-8

and should be sent to a URL like

    localhost/jobe/index.php/restapi/runs

For example, the following Linux `curl` command runs the C Hello World program:

    curl -d '{"run_spec": {"language_id": "c", "sourcefilename": "test.c", "sourcecode": "\n#include <stdio.h>\n\nint main() {\n    printf(\"Hello world\\n\");\n}\n"}}' -H "Content-type: application/json; charset-utf-8"  localhost/jobe/index.php/restapi/runs

## Updating Jobe

If you wish to update an existing version of Jobe to a new one, first put the
the client Moodle server into maintenance mode. Reboot the Jobe server. Then `cd`
into the Jobe directory, do a `git pull` to update the code, then run the
installer with the --purge option, i.e.

    sudo ./install --purge

Check that all is well by testing as in the section "Testing the install" above.
Lastly take the Moodle server out of maintenance mode again.

## Debugging

If you have problems installing Jobe, here are some things to check.

If the install script fails, check the error message. You should be able
    to read through the script and figure out what went wrong. Otherwise ...

1. Check the install went OK:

 1. Make sure your webserver has read access to the entire jobe subtree.
 1. Make sure your webserver has write access to jobe/files
 1. Make sure there exist users jobe and jobe00 through jobe09.
 1. Make sure there is a directory /home/jobe/runs owned by jobe and writeable
    by the webserver. It should not be readable or writeable by all.
 1. Make sure there is a directory /var/log/jobe.

If the install appears OK but testsubmit.py fails:

 1. If you get messages "Bad result object", something is fundamentally broken.
    Start by rebooting your server, and make sure Apache is running, e.g.
    by browsing to http://\<jobehost\>.
1.  Try pointing your browser at http://\<jobehost\>/jobe/index.php/restapi/languages
    This should return a JSON list of languages. If not, you may at least get
    a readable error message.
 1. You are running testsubmit.py with Python3, right?
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
 1. If you are getting Overloaded errors, then you can display the in-memory
    locks on the Jobe users with this PHP one-liner:
    ```php -r 'print_r(shm_get_var(shm_attach(ftok
      ("/var/www/html/jobe/application/libraries/LanguageTask.php", "j")), 1));'

If you still can't figure it out, email me (Richard Lobb; my gmail name is
trampgeek).

## An optional extra installation step

[For paranoid sysadmins only].

Submitted jobs can generally write files only into the temporary directory
created for their run within the '/home/jobe/runs'
directory. Exceptions to this rule are the /tmp, /var/tmp, /var/crash and
/run/lock directories all of which
conventionally can be written into by any Linux process.

The temporary working directory and any files in the writable directories
mentioned above are deleted on the termination of the run. However, depending on
the size of the various partitions and
the allowed maximum run time, it might in principle be
possible for a rogue process, or a deliberate attacker, to run the system
out of disk space in a particular partition (probably /tmp, which is usually
relatively small),
before the job terminates. That could in turn impact upon other jobs in
progress.

This possibility is considered very remote under normal circumstances. With typical
run times of a few seconds, jobs
time out long before they can fill up a main partition such as that housing
/home/jobe. Filling up /tmp is easier but jobs shouldn't generally be using
that directory, so a rogue process that fills it up shouldn't affect other users. In
either case, the space is freed as soon as the job terminates. Certainly this
is not a problem we have ever observed in
practice. However, it should be possible to protect against such an outcome by
setting disk quotas for the users jobe00, jobe01, ... jobe09 [The number
of such user accounts is defined by the parameter `jobe_max_users` in
`application/config/config.php`. The default value is 10.]
Instructions for installing the quota
management system and setting quotas are given in various places on the web, e.g.
[here](https://www.digitalocean.com/community/tutorials/how-to-enable-user-and-group-quotas).
The precise details will vary from system to system according to how the disk
partitions are set up; quotas should be
set for all jobe users on whatever partitions contain /home/jobe, /tmp, /var/tmp,
/var/crash and /run/lock.

## Securing the site

### Securing by means of a firewall

By default, Jobe is expected to run on an Intranet server
that is firewalled
to permit access only from specific authorised hosts. In this mode,
the client is assumed to be trusted and does not need to provide any form of
authorisation or authentication. It is also important to prevent the jobe
server from opening connections to other machines, so that a student
program cannot do nasty things like port-scanning within your Intranet.

Using ufw (Uncomplicated Firewall) a possible command
sequence that will restrict outgoing traffic to just a single nominated host
("some useful ip") on ports 80 and 443, allow ssh access (port 22) from anywhere and web
access to jobe (assumed to be on port 80) from just one specified client is the
following:

    ufw default reject outgoing
    ufw allow out proto tcp to <some_useful_ip> port 80,443
    ufw allow in 22/tcp
    ufw allow in proto tcp to any port 80 from <your_client_ip>
    ufw enable

In the above, <your\_client\_ip> is the host that is permitted to send jobs
to Jobe (e.g. a Moodle server with CodeRunner). <some\_useful\_ip> is
any server to which Jobe might need to connect in order to run/grade
student code. In the absence of such a server, that line should be omitted.

### Securing with API keys (rarely useful)

If you wish Jobe to serve multiple clients and do not wish to open a
specific port for each one you will need to configure the firewall to allow
incoming connections from anywhere but you should then also configure the rest-server
to require some form of authentication and authorisation. The various
ways of achieving this are discussed in the documentation of the
[rest-server plugin](https://github.com/chriskacerguis/codeigniter-restserver).

The simplest authorisation approach is to provide an API key on each request.
The client must then provide the key with each request in an X-API-Key header of
the form

    X-API-KEY: <key>

To set up Jobe to run in this way, proceed as follows:

 1. Make sure you installed the additional dependencies for API-key authentication
    given in the section "Installing the necessary dependencies". You need
    to be running a PHP version prior to PHP 7.2 (like that on Ubuntu 16.04 for
    example).

 1. Create a database called *jobe* and define a user with full access to it.

 1. Edit *application/config/database.php* to access your mysql server and
    the jobe database with the user credentials you defined in the previous
    step.

 1. Edit the file *application/config/rest.php* and set the configuration
    parameter *rest_enable_keys* to 1.

 1. Set up tables `keys` and `limits` as explained in *rest.php*. Populate
    the `keys` table with one or more API keys, which must then be used by
    any requests to the Jobe server.

If running in API-Key mode, you should still firewall the Jobe server to
prevent it opening any sockets to other machines.

### Other security mechanisms

If serving multiple clients, you may wish to restrict the use made of the
server by one or more clients. This can be done by
setting the *rest_enable_limits* parameter
in *application/config/rest.php* to non-zero.
Jobe will then limit the number of requests made
with any given key to the values set in
*application/config/per_method_limits.php*.

For this to work, the *jobe* database must contain an additional table *limits*,
defined with an SQL command like

	CREATE TABLE `limits` (
	  `id` int(11) NOT NULL AUTO_INCREMENT,
	  `uri` varchar(255) NOT NULL,
	  `count` int(10) NOT NULL,
	  `hour_started` int(11) NOT NULL,
	  `api_key` varchar(40) NOT NULL,
	  PRIMARY KEY (`id`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8;

You can turn off limit checking on a key-by-key basis by setting the *ignore_limits*
to FALSE in the *keys* table.

You should read the REST-server plugin documentation and the file
*application/config/rest.php* for other features available.


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
 1. memorylimit (usually 200 but 600 for Python3):
the maximum number of megabytes of memory the task can consume. This value is
used to set the Linux RLIMIT_STACK, RLIMIT_DATA and
RLIMIT_AS via the *setrlimit* system call. If the value is exceeded the job
is not aborted but malloc and/or mmap calls will fail to allocate more memory
with somewhat unpredictable results, although a segmentation fault is the most
likely outcome.
 1. numprocs (20): the maximum number of processes the task is allowed. If
this is exceeded the *fork* system call will fail with, again, somewhat
unpredictable outcomes.
 1. compileargs ([]): a list of string option values to pass to the compiler,
such as ["-Wall", "-std=c99"] for the C compiler. Meaningful only for compiled
languages. These arguments precede the name of the file to be compiled.
 1. linkargs ([]): a list of string option values to pass to the compiler,
such as ["-lm"] for the C compiler. These arguments follow the name of the file
to be compiled. Meaningful only for some compiled
languages, notably C and C++.
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
  <td>c</td><td>C</td><td>["-Wall", "-Werror", "-std=c99", "-x c"]</td><td></td>
<tr>
  <td>cpp</td><td>C++</td><td>["-Wall", "-Werror"]</td><td></td>
</tr>
<tr>
  <td>python2</td><td>Python2</td><td></td><td>["-BESs"]</td>
</tr>
<tr>
  <td>python3</td><td>Python3</td><td></td><td>["-BE"]</td>
</tr>
<tr>
  <td>java</td><td>Java</td><td></td><td>["-Xrs", "-Xss8m", "-Xmx200m"]</td>
</tr>
<tr>
  <td>nodejs</td><td>JavaScript (nodejs)</td><td></td><td>["--use_strict"]</td>
</tr>
<tr>
  <td>octave</td><td>Octave (matlab variant)</td><td></td><td>["--norc", "--no-window-system", "--silent", "-H"]</td>
</tr>
<tr>
  <td>php</td><td>PHP</td><td></td><td>["--no-php-ini"]</td>
</tr>
<tr>
  <td>pascal</td><td>Free Pascal</td><td>["-vew", "-Se"]</td><td></td>
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

        { "compileargs": ["-Wall", "-Werror", "-std=c89"] }

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
   must be returned in $this->executableFileName; alternatively
   $this->cmpinfo should be set to an appropriate error message; any non-empty
   string is taken as a compile error. Interpreted languages might do nothing
   or might copy the program.

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

## Change Log

### Version 1.2

Fixed bug with Java when correct source file name supplied in the request
(rename of file to itself was failing). Thanks Paul Denny.
Replaced uses of Moodle coding_exception with generic exception. Again thanks
Paul Denny.

Fixed bug in C++ task - invalid language type being passed to compiler.

Updated CodeIgniter Rest Server to latest version.

Added code to load limit data from a config file "per\_method\_limits.php" to
support per-API-key limits on the number of calls that can be made to the
restapi's POST and PUT entry points per hour. Updated the documentation to
explain how to turn on API-key authorisation and per-method limits.

### Version 1.2.2

Added code to support CORS (Cross Origin Resource Sharing), i.e.,
in-browser JavaScript requests from arbitrary domains.

### Version 1.2.3

Fixed bug in how Java class names (and hence source file
names) were inferred from the source code (main classes that implemented an
interface or extended a subclass were not handled correctly). Also the filename
field in the REST API runspec is now optional; if provided, it is trusted
and used as-is, but if not supplied or if an empty string is supplied, Jobe
now calls a language-specific function to provide a filename from the sourcecode.
[Usually this is just something generic like prog.cpp, prog.py etc]

### Version 1.2.4

Fixed issue with runguard that prevented use of pthreads library in C programs.,

### Version 1.3

Pascal support added by Fedor Lyanguzov (thanks Fedor)

Good luck!

### Version 1.3.1

Minor patches to ensure PHP7 compability. Install instruction in readme.md
still relate to PHP5, however.

### Version 1.3.2

Change Java config parameters to allow Java 8 to run (more memory and
more processes).

### Version 1.3.3

Remove inline declaration of readoptarg in runguard.c (causing compile errors
with most recent gcc versions). Documentation tweaks.

### Version 1.3.4

Fix serious security flaw in runguard + my use of it.

### Version 1.3.5

1. Fix broken caching of language versions (wasting time on each submission).
1. Improve identification of language versions; 'Unknown' is now given as the
language version if a language get-version command runs but produces output
in an unexpected format. Formerly such languages were deemed invalid.
1. Change Java task so supplied memlimit is ignored, leaving JVM to manage its
own memory.
1. Add 'getLanguages' to simpletest.py and testsubmit.py.

### Version 1.3.5+ 16 June 2017

 1. Improve installer to handle installation on servers with less permissive
    access rights than Ubuntu 16.04.
 1. Delete any files created in /tmp, /var/tmp, /run/lock and /var/crash
    on completion of a run.
 1. Limit maximum CPU time for any one Jobe to 30 secs (config constant).

Thanks Kai-Cheung Leung for the first two of those additions.

### Version 1.3.6 21 June 2017

 1. Minimum PHP version is now required to be 5.5. (This is now checked in the installer.)
 1. Compilation of the Student's code is now also done in the runguard sandbox.
    This provides an additional layer of security.

Thanks Tim Hunt for most of the work in this addition.

### 1.3.6+

 1. Tune retry count for better performance under overload.
 1. Documentation updates
 1. Tweak installer for Centos detection of web server

### 1.4.0

  1. Tweaks to allow full utf-8 output to be returned, provided Apache's LANG
     variable is set to a UTF-8 compatible value.

### 1.4.1

  1. Merged in switch to pylint3/python3 completely (thanks Garth Williamson)

### 1.4.2

  1. Bug fix: Jobe server overload was being incorrectly reported as a Runguard
     error ("No user jobe-1").
### 1.4.3

  1. Fix bug in testsubmit.php when used with latest pylint3.
  1. Document dependency script for Ubuntu 18.04 plus limitations due to missing
     mcrypt.

### 1.5.0

  1. Move to latest versions of CodeIgniter and RestServer frameworks, primarily
     to fix bug with PHP versions > 7.1 no longer supporting mcrypt library,
     but also for improved security and error handling.

### 1.6.0

  1. Change file cache directory from /var/www/html/jobe/files to /home/jobe/files
  1. Change file cache to use a 3 level hierarchy, using the first 4 chars of
     the MD5 file-id (2 pairs of 2) for the directory names to improve lookup
     performance when there are many files.
  1. Implement a simple cache clean mechanism that deletes all files that
     haven't been used for 2 or more days whenever less than 5% of the disk
     space is free.
  1. Document in restapi that use of *check_file* to confirm existence of a
     required file before a run is unsafe, as the file might be removed by
     the cache cleaner between the two runs.

### 1.6.0+ (5 December 2019)

  1. Correct bad JSON in documentation (was using single quoted strings).

### 1.6.1 (14 April 2020)

  1. Tweak handling of timeouts to kill jobs after a wall-clock time in excess
     of twice the given max_cpu_time
  1. Document issue with handling of resource limits. Jobe is inappropriately
     applying the compile resource limits even for non-compile tasks.
     However, fixing this might break existing questions and it's not a
     serious problem.
  1. Correct bad JSON in documentation that used single-quoted strings.
  1. Add /var/lock to the list of directories to be cleaned on task exit.
     While it's usually a symbolic link to /run/lock, apparently that's not
     always the case.
  1. Accept Java programs that use "static public" in main() declaration rather
     than the more usual "public static".
  1. Fix deprecation warning with PHP 7.4 (and possibly earlier) resulting from
     loading the JSON-encoded language cache file into an object rather than an
     associative array.

### 1.6.2 (16 May 2020)

  1. Increase memory limit for Python3 to 600 MB. Document.

### 1.6.2+ (24 May 2020)

  1. Change install instructions to install non-GUI Octave.

### 1.6.3 (20 November 2020)

  1. Prevent privilege escalation attacks via cputime parameter, [issue #39](https://github.com/trampgeek/jobe/issues/39).
  1. Change the invalid nodejs program in testsubmit.py to be even more invalid,
     so it fails to run with all versions of nodejs.

### 1.6.4 (22 January 2021)

  1. Workaround for bug in py_compile (https://bugs.python.org/issue38731)
that results in multiple error messages when a python syntax check fails.
