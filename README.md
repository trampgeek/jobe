# JOBE

Version: 0.3 alpha May 2014

Author: Richard Lobb, University of Canterbury, New Zealand.

## Introduction

Jobe (short for Job Engine) is a server that supports running of small
compile-and-run jobs in a variety of programming languages. It is being
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

Jobe is still under development. The current alpha version implements
enough of the API to provide the services needed by CodeRunner. Only 
immediate-mode runs are supported, with run results being returned with the
response to the POST of the run requests. Run results are not retained by
the server (unless *run\_spec.debug* is true; see the API), so 
*get\_run\_status* always returns 404 not found.  C, Python3, Python2, Octave
and Java have been
tested at this stage, and untested code exists to support C++ and Matlab.

Sandboxing is fairly basic. It uses the [domjudge](http://domjudge.org) 
*runguard* program to run student jobs with restrictions on resource
allocation (memory, processes, cpu time) as a low-privileged user.
However it does not restrict any system calls and the task is not yet run
in a chroot jail.

## Installation

**WARNING** This current version is intended for installing on a
server that is firewalled to allow connections ONLY from authorised client
machines. If you install it on a machine without such firewalling,
anyone will be able to connect to your machine and run their own code
on it! **CAVEAT EMPTOR!**

Jobe is implemented using Ellis Lab's [codeigniter](http://codeigniter.com) plus the
[RESTserver plugin](https://github.com/philsturgeon/codeigniter-restserver) from
Phil Sturgeon. It uses Jaap Eldering's and Keith Johnson's *Runguard*
module from the programming contest server (DOMJudge)[http://domjudge.org] 
as a sandbox to limit resource use by submitted jobs.

Jobe runs only on Linux, which must have the Apache web server
installed and running. PHP must have been compiled with the System V
Semaphone and shared-memory functions enabled
(see here)[http://www.php.net/manual/en/sem.setup.php].
The Python3 and the C development system must also be
installed.

*** TBS *** Discussion on use of cgroups (currently disabled)

The first step is to clone the project in the web root directory (assumed
to be /var/www/html although hopefully other web roots will work). Do not clone
the project elsewhere and attempt to add it to web root with symbolic links.
That breaks this installer.

To clone the project:

    cd /var/www/html
    sudo git clone https://github.com/trampgeek/jobe.git

Installation is performed by the install script, which must be run as root
so that it can add the required jobe run users (jobe00, jobe01, etc) and
set-up a jobe-sudoers file in /etc/sudoers.d that allows the web server
to execute the runguard program as root and to kill any residual jobe
processes from the run.

    cd /var/www/html/jobe
    sudo ./install

To test the installation, first try running the tester with the command

    python3 testsubmit.py

All going well, you should then be able to copy the *testsubmit.py* file to
any client machine that is allowed to access the jobe server, edit the line

    JOBE_SERVER = 'localhost'

to reference the JOBE_SERVER, e.g. by replacing *localhost* with its IP
number, and re-run the tester with the same command from the client machine.

## Debugging

At this stage (Alpha release) I've no idea what will go wrong for other
people, but here are some of the things I did during development, which may
be of use to you.

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

Good luck!

Richard

