# JOBE

Version: 0.2 alpha May 2014

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
enough of the API to provide the services needed by CodeRunner (hopefully --
this is still being developed). Only 
immediate-mode runs are supported, with run results being returned with the
response to the POST of the run requests. Run results are not retained by
the server, so 
get_run_status always returns 404 not found. Only C and Python3 have been
tested at this stage, although untested code exists to support Python2, Java
Matlab and Octave.

Sandboxing is rather basic. It uses the [domjudge](http://domjudge.org) 
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
module from the programming contest server (DOMJudge )[http://domjudge.org] 
as a sandbox to limit resource use by submitted jobs.

Jobe runs only on Linux, which must have the Apache web server
installed and running. Python3 and the C development system must also be
installed.

*** TBS *** Discussion on use of cgroups (current disabled)

Installation is performed by the install script, which must be run as root
so that it can add the required jobe run users (jobe00, jobe01, etc) and
set the runguard executable to setuid root so that it can change the user
to one of the jobe<n> users when running a job.

    cd /var/www/jobe
    sudo ./install