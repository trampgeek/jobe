#! /usr/bin/env python3
# coding=utf-8
''' A tester and demo program for jobe
    Richard Lobb
    2/2/2015

    Modified 6/8/2015 to include --verbose command line parameter, to do
    better file upload and pylint testing and to improve error messages
    in some cases.

    Modified 7/11/2016 by Tim Hunt. Allow selection of languages to run
    from the command line, and use a non-zero exit code (number of
    failures + number of exceptions) if not all tests pass.

    Usage:
    To run all tests:
        ./testsubmit.py
    or
        ./testsubmit.py --verbose

    To run selected tests by language:
        ./testsubmit.py python3 octave --verbose
    (Use of --verbose is optional here.)
'''

from urllib.request import urlopen
from urllib.parse import urlencode
from urllib.error import HTTPError
import json
import sys
import http.client
from threading import Thread
from hashlib import md5
import copy
from base64 import b64encode

# Set VERBOSE to true to get a detailed report on each run as it is done
# Can also be set True with --verbose command argument.
VERBOSE = False

# Set DEBUGGING to True to instruct Jobe to use debug mode, i.e., to
# leave all runs (commands, input output etc) in /home/jobe/runs, rather
# than deleting each run as soon as it is done.
DEBUGGING = False

# Set JOBE_SERVER to the Jobe server URL.
# If Jobe expects an X-API-Key header, set API_KEY to a working value and set
# USE_API_KEY to True.
API_KEY = '2AAA7A5415B4A9B394B54BF1D2E9D'  # A working (100/hr) key on Jobe2

USE_API_KEY = True
JOBE_SERVER = 'localhost'
RUNS_RESOURCE = '/jobe/index.php/restapi/runs/'

#JOBE_SERVER = 'jobe2.cosc.canterbury.ac.nz'

# The next constant controls the maximum number of parallel submissions to
# throw at Jobe at once. Numbers less than or equal to the number of Jobe
# users (currently 10) should be safe. Larger numbers might cause
# Overload responses.
NUM_PARALLEL_SUBMITS = 10

GOOD_TEST = 0
FAIL_TEST = 1
EXCEPTION = 2

JAVA_PROGRAM = """public class Thing {
    private String message;
    public Thing(String message) {
        this.message = message;
    }
    public void printme() {
        System.out.println(message);
    }
}
"""

JAVA_PROGRAM_MD5 = md5(JAVA_PROGRAM.encode('utf-8')).hexdigest()

# ===============================================
#
# Test List
#
# ===============================================

TEST_SET = [

# ======= PYTHON3 Tests ===============
{
    'comment': 'Valid Python3',
    'language_id': 'python3',
    'sourcecode': r'''print("Hello world!")
''',
    'sourcefilename': 'test.py',
    'expect': { 'outcome': 15, 'stdout': 'Hello world!\n' }
},


{
    'comment': 'Python3 with stdin',
    'language_id': 'python3',
    'sourcecode': r'''print(input())
print(input())
''',
    'input': 'Line1\nLine2\n',
    'sourcefilename': 'test.py',
    'expect': { 'outcome': 15, 'stdout': 'Line1\nLine2\n' }
},

{
    'comment': 'Syntactically invalid Python3',
    'language_id': 'python3',
    'sourcecode': r'''print("Hello world!"
''',
    'sourcefilename': 'test.py',
    'expect': { 'outcome': 11 }
},

{
    'comment': 'Python3 runtime error',
    'language_id': 'python3',
    'sourcecode': r'''data = [1, 2]
print(data[2])
''',
    'sourcefilename': 'test.py',
    'expect': { 'outcome': 12 }
},

{
    'comment': 'Python3 file I/O',
    'language_id': 'python3',
    'sourcecode': r'''with open('testoutput.txt', 'w') as output:
    output.write("One fish\nTwo fish\n")
with open('testoutput.txt') as input:
    print(input.read(), end='')
''',
    'sourcefilename': 'test.py',
    'expect': { 'outcome': 15, 'stdout': 'One fish\nTwo fish\n' }
},

{
    'comment': 'Testing use of interpreter args with Python3',
    'language_id': 'python3',
    'sourcecode': r'''
''',
    'sourcefilename': 'test.py',
    'expect': { 'outcome': 15, 'stdout': 'Blah\n' },
    'parameters': {'interpreterargs': [r'-c "print(\"Blah\")"'] }
},

{
    'comment': 'Testing use of runargs args with Python3',
    'language_id': 'python3',
    'sourcecode': r'''
import sys
for arg in sys.argv[1:]:
    print(arg)
''',
    'sourcefilename': 'test.py',
    'expect': { 'outcome': 15, 'stdout': 'Arg1\nArg2\n' },
    'parameters': {'runargs': ['Arg1', 'Arg2'] }
},

{
    'comment': 'Python3 program with customised timeout',
    'language_id': 'python3',
    'sourcecode': r'''from time import clock
t = clock()
while clock() < t + 10: pass  # Wait 10 seconds
print("Hello Python")
''',
    'sourcefilename': 'test.py',
    'parameters': {'cputime':15},
    'expect': { 'outcome': 15, 'stdout': '''Hello Python
'''}
},

{
    'comment': 'Python3 program with support files',
    'language_id': 'python3',
    'files': [
        ('randomid0129798', 'The first file\nLine 2'),
        ('randomid0980128', 'Second file')],
    'sourcecode': r'''print(open('file1').read())
print(open('file2').read())
''',
    'file_list': [('randomid0129798', 'file1'),('randomid0980128', 'file2')],
    'sourcefilename': 'test.py',
    'expect': { 'outcome': 15, 'stdout': '''The first file
Line 2
Second file
'''}
},

{
    'comment': 'Valid Python3/pylint program',
    'language_id': 'python3',
    'sourcecode': """__student_answer__ = '''\"\"\"Doc string\"\"\"
GLOBAL = 20
print("GLOBAL =", GLOBAL)
'''

import subprocess
import os

def check_code(s):
    try:
        source = open('source.py', 'w')
        source.write(__student_answer__)
        source.close()
        env = os.environ.copy()
        os.mkdir('Home')
        env['HOME'] = os.getcwd() + '/Home'
        result = subprocess.check_output(['pylint3', '--reports=no', 'source.py'],
            universal_newlines=True, stderr=subprocess.STDOUT, env=env)
        # Fix problem with versions of pylint that insist on telling you
        # what config file they're using
        result = result.replace('Using config file /etc/pylintrc', '')
    except Exception as e:
        result = str(e)

    lines = result.strip().split('\\n')
    for line in lines:
        if line.strip() and not line.startswith('Warning: option'):
            print(line)
            return False

    return True

if check_code(__student_answer__):
    print("Yay!")
""",
    'parameters': {'memorylimit': 200000},
    'sourcefilename': 'prog.py',
    'expect': { 'outcome': 15, 'stdout': 'Yay!\n' }
},

{
    'comment': 'Invalid Python3/pylint program',
    'language_id': 'python3',
    'sourcecode': """__student_answer__ = '''# Alas no docstring
GLOBAL = 20
print("GLOBAL =", GLOBAL)
'''

import subprocess
import os

def check_code(s):
    try:
        source = open('source.py', 'w')
        source.write(__student_answer__)
        source.close()
        env = os.environ.copy()
        os.mkdir('Home')
        env['HOME'] = os.getcwd() + '/Home'
        result = subprocess.check_output(['pylint3', '--reports=no', 'source.py'],
            universal_newlines=True, stderr=subprocess.STDOUT, env=env)
        # Fix problem with versions of pylint that insist on telling you
        # what config file they're using
        result = result.replace('Using config file /etc/pylintrc', '')
    except Exception as e:
        result = str(e)

    lines = result.strip().split('\\n')
    for line in lines:
        if not line.startswith('Warning: option'):
            print("pylint doesn't approve of your program")
            return False

    return True

if check_code(__student_answer__):
    print("Yay!")
""",
    'parameters': {'memorylimit': 200000},
    'sourcefilename': 'prog.py',
    'expect': { 'outcome': 15, 'stdout': "pylint doesn't approve of your program\n" }
},

{
    'comment': 'UTF-8 output from Python3 (will fail unless Jobe set up for UTF-8)',
    'language_id': 'python3',
    'sourcecode': r'''print("Un rôle délétère")
''',
    'sourcefilename': 'test.py',
    'expect': { 'outcome': 15, 'stdout': "Un rôle délétère\n" }
},

# ======= C Tests ===============
{
    'comment': 'Test good C hello world',
    'language_id': 'c',
    'sourcecode': r'''#include <stdio.h>
int main() {
    printf("Hello world\nIsn't this fun!\n");
}
''',
    'sourcefilename': 'prog.c',
    'expect': { 'outcome': 15, 'stdout': "Hello world\nIsn't this fun!\n" }
},

{
    'comment': 'Test compile error C hello world',
    'language_id': 'c',
    'sourcecode': r'''#include <stdio.h>
int main() {
    printf("Hello world\nIsn't this fun!\n")
}
''',
    'sourcefilename': 'prog.c',
    'expect': { 'outcome': 11 }
},

{
    'comment': 'Test use of compileargs with C',
    'language_id': 'c',
    'sourcecode': r'''#include <stdio.h>
int main() {
    printf("Hello world\nIsn't this fun!\n");
    /* No return so shouldn't compile in ANSI C */
}
''',
    'sourcefilename': 'prog.c',
    'expect': { 'outcome': 11 },
    'parameters': { 'compileargs': ['std=c89'] }
},

{
    'comment': 'Test runtime error C hello world',
    'language_id': 'c',
    'sourcecode': r'''#include <stdio.h>
int main() {
    printf("Hello world\nIsn't this fun!\n");
    char* s = NULL;
    printf("%c", *s);
}
''',
    'sourcefilename': 'prog.c',
    'expect': { 'outcome': 12 }
},

{
    'comment': 'Test timelimit on C',
    'language_id': 'c',
    'sourcecode': r'''#include <stdio.h>
int main() {
    while(1) {};
}
''',
    'sourcefilename': 'prog.c',
    'expect': { 'outcome': 13 }
},

{
    'comment': 'Test outputlimit on C',
    'language_id': 'c',
    'sourcecode': r'''#include <stdio.h>
int main() {
    while(1) { printf("Hello"); };
}
''',
    'sourcefilename': 'prog.c',
    'expect': { 'outcome': 13 }
},

{
    'comment': 'Memory limit exceeded in C (seg faults)',
    'language_id': 'c',
    'sourcecode': r'''#include <stdio.h>
#include <stdlib.h>
// Will try to allocate 500MB; default limit is 200MB
#define CHUNKSIZE 500000000

int main() {
    char* p = malloc(CHUNKSIZE);
    if (p == NULL) {
        printf("Memory limit worked\n");
    } else {
        printf("Oh dear, the malloc worked");
    }
}

''',
    'sourcefilename': 'prog.c',
    'expect': { 'outcome': 15, 'stdout': 'Memory limit worked\n' }
},

{
    'comment': 'Infinite recursion (stack error) on C',
    'language_id': 'c',
    'sourcecode': r'''#include <stdlib.h>
#include <assert.h>

void silly(int i) {
    int j = i + 1;
    if (j != 0) {
        silly(j);
    } else {
        silly(j + 1);
    }
}

int main() {
    silly(3);
}
''',
    'sourcefilename': 'prog.c',
    'expect': { 'outcome': 12 }
},

{
    'comment': 'C program controlled forking',
    'language_id': 'c',
    'sourcecode': r'''#include <linux/unistd.h>
#include <unistd.h>
#include <stdio.h>
int main() {
    int successes = 0, failures = 0;
    for (int i = 0; i < 1000; i++) {
        int pid = fork();
        if (pid == -1) {
            failures += 1;
        }
        else if (pid == 0) {
            while (1) {};  // Child loops
        }
        else {
            successes += 1;
        }
    }
    printf("%d forks succeeded, %d failed\n", successes, failures);
}''',
    'sourcefilename': 'test.c',
    'parameters': { 'numprocs': 10 },
    'expect': { 'outcome': 15, 'stdout': '9 forks succeeded, 991 failed\n' }
},


{
    'comment': 'A C program with ASCII non-UTF-8-compatible output',
    'language_id': 'c',
    'sourcecode': r'''#include <stdio.h>
int main() {
    printf("Hello world\n\01\006\300\311\n");
}
''',
    'sourcefilename': 'prog.c',
    'expect': { 'outcome': 15, 'stdout': "Hello world\n\\x01\\x06\\xc0\\xc9\n" }
},

# ================= Octave tests ==================
{
    'comment': 'Valid Octave',
    'language_id': 'octave',
    'sourcecode': r'''_blah_ = 0;  % So octave doesn't expect just a function
function sq = sqr(n)
    sq = n * n;
end

fprintf('%d\n%d\n%d\n', sqr(-3), sqr(11), sqr(0));
''',
    'parameters': {'memorylimit': 200000},
    'sourcefilename': 'jobe_test.m',
    'expect': { 'outcome': 15, 'stdout': '9\n121\n0\n' }
},

{
    'comment': 'octave with stdin',
    'language_id': 'octave',
    'sourcecode': r'''fprintf('%s\n%s\n', input('', 's'), input('', 's'));
''',
    'parameters': {'memorylimit': 200000},
    'input': 'Line1\nLine2\n',
    'sourcefilename': 'jobe_test.m',
    'expect': { 'outcome': 15, 'stdout': 'Line1\nLine2\n' }
},

{
    'comment': 'Syntactically invalid Octave (treated as runtime error)',
    'language_id': 'octave',
    'sourcecode': r'''s = "hi there';
''',
    'parameters': {'memorylimit': 200000},
    'sourcefilename': 'jobe_test.m',
    'expect': { 'outcome': 12 }
},


# ================= NodeJS tests ==================
{
    'comment': 'Syntactically valid Nodejs hello world',
    'language_id': 'nodejs',
    'sourcecode': r'''console.log('Hello world!');
''',
    'sourcefilename': 'test.js',
    'parameters': {'memorylimit': 1000000},
    'expect': { 'outcome': 15, 'stdout': 'Hello world!\n' }
},

{
    'comment': 'Syntactically invalid (non-strict) Nodejs',
    'language_id': 'nodejs',
    'sourcecode': r'''s = 'Hello world!'
console.log(s)
''',
    'sourcefilename': 'test.js',
    'parameters': {'memorylimit': 1000000},
    'expect': { 'outcome': 12 }
},

# ================= PHP tests ==================
{
    'comment': 'Correct Php program ',
    'language_id': 'php',
    'sourcecode': r'''<!DOCTYPE html>
<html>
<head></head>
<body>
<h1>Heading</h1>
<p><?php echo "A paragraph"; ?></p>
</body>
</html>
''',
    'sourcefilename': 'test.php',
    'parameters': {'cputime':15},
    'expect': { 'outcome': 15, 'stdout': '''<!DOCTYPE html>
<html>
<head></head>
<body>
<h1>Heading</h1>
<p>A paragraph</p>
</body>
</html>
'''}
},

{
    'comment': 'Syntactically incorrect Php program ',
    'language_id': 'php',
    'sourcecode': r'''<!DOCTYPE html>
<html>
<head></head>
<body>
<h1>Heading</h1>
<p><?php echo "A paragraph' ?></p>
</body>
</html>
''',
    'sourcefilename': 'test.php',
    'parameters': {'cputime':15},
    'expect': { 'outcome': 11 }
},


{
    'comment': 'Syntactically incorrect Php program ',
    'language_id': 'php',
    'sourcecode': r'''<!DOCTYPE html>
<html>
<head></head>
<body>
<h1>Heading</h1>
<p><?php echo "A paragraph' ?></p>
</body>
</html>
''',
    'sourcefilename': 'test.php',
    'parameters': {'cputime':15},
    'expect': { 'outcome': 11 }
},

# ================= Java tests ==================
{
    'comment': 'Correct Java program ',
    'language_id': 'java',
    'sourcecode': r'''
public class Test {
    public static void main(String[] args) {
        System.out.println("What a lot of code I need to write.");
    }
}
''',
    'sourcefilename': 'Test.java',
    'parameters': {'cputime':10},
    'expect': { 'outcome': 15, 'stdout': '''What a lot of code I need to write.
'''}
},


{
    'comment': 'Correct Java program without supplied sourcefilename ',
    'language_id': 'java',
    'sourcecode': r'''
public class Test {
    public static void main(String[] args) {
        System.out.println("What a lot of code I need to write.");
    }
}
''',
    'parameters': {'cputime':10},
    'expect': { 'outcome': 15, 'stdout': '''What a lot of code I need to write.
'''}
},

{
    'comment': 'Syntactically incorrect Java program ',
    'language_id': 'java',
    'sourcecode': r'''
public class Test {
    public static void main(String[] args) {
        System.out.println('What a lot of code I need to write.);
}
''',
    'sourcefilename': 'Test.java',
    'parameters': {'cputime':10},
    'expect': { 'outcome': 11 }
},

{
    'comment': 'Java program with a support class (.java)',
    'language_id': 'java',
    'sourcecode': r"""
// A Java program with a support class
public class Blah {
    public static void main(String[] args) {
        Thing thing = new Thing("Farewell cruel world");
        thing.printme();
    }
}
""",
    'files': [
        (JAVA_PROGRAM_MD5, JAVA_PROGRAM)
    ],
    'file_list': [(JAVA_PROGRAM_MD5, 'Thing.java')],
    'parameters': {'cputime':10},
    'expect': { 'outcome': 15, 'stdout': '''Farewell cruel world
'''}
},

{
    'comment': 'Java program with Unicode output (will fail unless Jobe set up for UTF-8) ',
    'language_id': 'java',
    'sourcecode': r'''
public class Test {
    public static void main(String[] args) {
        System.out.println("Un rôle délétère");
    }
}
''',
    'sourcefilename': 'Test.java',
    'parameters': {'cputime':10},
    'expect': { 'outcome': 15, 'stdout': "Un rôle délétère\n"}
},

#================= C++ tests ======================
{
    'comment': 'Test good C++ hello world',
    'language_id': 'cpp',
    'sourcecode': r'''#include <iostream>
using namespace std;
int main() {
    cout << "Hello world\nIsn't this fun!\n";
}
''',
    'sourcefilename': 'prog.cpp',
    'expect': { 'outcome': 15, 'stdout': "Hello world\nIsn't this fun!\n" }
},

{
    'comment': 'Test compile error C++ hello world',
    'language_id': 'c',
    'sourcecode': r'''#include <iostream>
int main() {
    cout << "Hello world\nIsn't this fun!\n";
}
''',
    'sourcefilename': 'prog.c',
    'expect': { 'outcome': 11 }
},

#================ Pascal tests ====================
{
    'comment': 'Good Hello world Pascal test',
    'language_id': 'pascal',
    'sourcecode': r'''begin
writeln('Hello world!');
end.
''',
    'sourcefilename': 'prog.pas',
    'expect': { 'outcome': 15, 'stdout': "Hello world!\n" }
},

{
    'comment': 'Fail Hello world Pascal test',
    'language_id': 'pascal',
    'sourcecode': r'''begin
writeln('Hello world!');
end.
''',
    'sourcefilename': 'prog.pas',
    'expect': { 'outcome': 11 }
}

]

#==========================================================================
#
# Now the tester code
#
#==========================================================================


def check_parallel_submissions():
    '''Check that we can submit several jobs at once to Jobe with
       the process limit set to 1 and still have no conflicts.
    '''

    job = {
        'comment': 'C program to check parallel submissions',
        'language_id': 'c',
        'sourcecode': r'''#include <stdio.h>
#include <unistd.h>
int main() {
    printf("Hello 1\n");
    sleep(2);
    printf("Hello 2\n");
}''',
        'sourcefilename': 'test.c',
        'parameters': { 'numprocs': 1 },
        'expect': { 'outcome': 15, 'stdout': 'Hello 1\nHello 2\n' }
    }

    threads = []
    print("\nChecking parallel submissions")
    for child_num in range(NUM_PARALLEL_SUBMITS):
        print("Doing child", child_num)
        def run_job():
            this_job = copy.deepcopy(job)
            this_job['comment'] += '. Child' + str(child_num)
            run_test(job)

        t = Thread(target=run_job)
        threads.append(t)
        t.start()

    for t in threads:
        t.join()
    print("All done")




def is_correct_result(expected, got):
    '''True iff every key in the expected outcome exists in the
       actual outcome and the associated values are equal, too'''
    for key in expected:
        if key not in got or expected[key] != got[key]:
            return False
    return True

# =============================================================

def http_request(method, resource, data, headers):
    '''Send a request to Jobe with given HTTP method to given resource on
       the currently configured Jobe server and given data and headers.
       Return the connection object. '''
    if USE_API_KEY:
            headers["X-API-KEY"] = API_KEY
    connect = http.client.HTTPConnection(JOBE_SERVER)
    connect.request(method, resource, data, headers)
    return connect


def check_file(file_id):
    '''Checks if the given fileid exists on the server.
       Returns status: 204 denotes file exists, 404 denotes file not found.
    '''

    resource = '/jobe/index.php/restapi/files/' + file_id
    headers = {"Accept": "text/plain"}
    try:
        connect = http_request('HEAD', resource, '', headers)
        response = connect.getresponse()

        if VERBOSE:
            print("Response to getting status of file ", file_id, ':')
            content = ''
            if response.status != 204:
                content =  response.read(4096)
            print(response.status, response.reason, content)

        connect.close()

    except HTTPError:
        return -1

    return response.status



def put_file(file_desc):
    '''Put the given (file_id, contents) to the server. Throws
       an exception to be caught by caller if anything fails.
    '''
    file_id, contents = file_desc
    contentsb64 = b64encode(contents.encode('utf8')).decode(encoding='UTF-8')
    data = json.dumps({ 'file_contents' : contentsb64 })
    resource = '/jobe/index.php/restapi/files/' + file_id
    headers = {"Content-type": "application/json",
               "Accept": "text/plain"}
    connect = http_request('PUT', resource, data, headers)
    response = connect.getresponse()
    if VERBOSE or response.status != 204:
        print("Response to putting", file_id, ':')
        content = ''
        if response.status != 204:
            content =  response.read(4096)
        print(response.status, response.reason, content)
    connect.close()


def runspec_from_test(test):
    """Return a runspec corresponding to the given test"""
    runspec = {}
    for key in test:
        if key not in ['comment', 'expect', 'files']:
            runspec[key] = test[key]
    if DEBUGGING:
        runspec['debug'] = True
    return runspec


def run_test(test):
    '''Execute the given test, checking the output'''

    runspec = runspec_from_test(test)
    # First put any files to the server
    for file_desc in test.get('files', []):
        put_file(file_desc)
        response_code = check_file(file_desc[0])
        if response_code != 204:
            print("******** Put file/check file failed ({}). File not found.****".
                  format(response_code))

    # Prepare the request

    data = json.dumps({ 'run_spec' : runspec })
    response = None
    content = ''

    # Do the request, returning EXCEPTION if it broke
    ok, result = do_http('POST', RUNS_RESOURCE, data)
    if not ok:
        return EXCEPTION

   # If not an exception, check the response is as specified

    if is_correct_result(test['expect'], result):
        if VERBOSE:
            display_result(test['comment'], result)
        else:
            print(test['comment'] + ' OK')
        return GOOD_TEST
    else:
        print("\n***************** FAILED TEST ******************\n")
        print(result)
        display_result(test['comment'], result)
        print("\n************************************************\n")
        return FAIL_TEST


def do_http(method, resource, data=None):
    """Send the given HTTP request to Jobe, return a pair (ok result) where
       ok is true if no exception was thrown, false otherwise and
       result is a dictionary of the JSON decoded response (or an empty
       dictionary in the case of a 204 response.
       As a special-case hack for testing 400 error conditions, if the
       decoded JSON response is a string (which should only occur when an
       error has occurred), the returned result string is prefixed by the
       response code.
    """
    result = {}
    ok = True
    headers = {"Content-type": "application/json; charset=utf-8",
               "Accept": "application/json"}
    try:
        connect = http_request(method, resource, data, headers)
        response = connect.getresponse()
        if response.status != 204:
            content = response.read().decode('utf8')
            if content:
                result = json.loads(content)
        if isinstance(result, str):
            result = str(response.status) + ': ' + result
        connect.close()

    except (HTTPError, ValueError) as e:
        print("\n***************** HTTP ERROR ******************\n")
        if response:
            print(' Response:', response.status, response.reason, content)
        else:
            print(e)
        ok = False
    return (ok, result)


def trim(s):
    '''Return the string s limited to 10k chars'''
    MAX_LEN = 10000
    if len(s) > MAX_LEN:
        return s[:MAX_LEN] + '... [etc]'
    else:
        return s


def display_result(comment, ro):
    '''Display the given result object'''
    print(comment)
    if not isinstance(ro, dict) or 'outcome' not in ro:
        print("Bad result object", ro)
        return

    outcomes = {
        0:  'Successful run',
        11: 'Compile error',
        12: 'Runtime error',
        13: 'Time limit exceeded',
        15: 'Successful run',
        17: 'Memory limit exceeded',
        19: 'Illegal system call',
        20: 'Internal error, please report',
        21: 'Server overload. Excessive parallelism?'}

    code = ro['outcome']
    print("Jobe result: {}".format(outcomes[code]))
    print()
    if ro['cmpinfo']:
        print("Compiler output:")
        print(ro['cmpinfo'])
        print()
    else:
        if ro['stdout']:
            print("Output:")
            print(trim(ro['stdout']))
        else:
            print("No output")
        if ro['stderr']:
            print()
            print("Error output:")
            print(trim(ro['stderr']))


def do_get_languages():
    """List all languages available on the jobe server"""
    print("Supported languages:")
    resource = '/jobe/index.php/restapi/languages'
    ok, lang_versions = do_http('GET', resource)
    if not ok:
        print("**** An exception occurred when getting languages ****")
    else:
        for lang, version in lang_versions:
            print("    {}: {}".format(lang, version))
    print()


def check_bad_cputime():
    """Check that setting the cputime parameter in a run request to a value
       greater than 50 (the default configured cputime_upper_limit_secs)
       the appropriate 400 response occurs
    """
    test = {
    'comment': 'C program run with illegal cputime',
    'language_id': 'c',
    'sourcecode': r'''#include <stdio.h>
int main() {
    printf("Hello world\nIsn't this fun!\n");
}
''',
    'sourcefilename': 'test.c',
    'parameters': {'cputime': 51}
}
    runspec = runspec_from_test(test)
    data = json.dumps({ 'run_spec' : runspec })
    print("\nTesting a submission with an excessive cputime parameter")
    ok, result = do_http('POST', RUNS_RESOURCE, data)
    if result == "400: cputime exceeds maximum allowed on this Jobe server":
        print("OK")
    else:
        print("********** TEST FAILED **************")
        print("Return value from do_http was ", (ok, result))


def main():
    '''Every home should have one'''
    global VERBOSE
    do_get_languages()
    langs_to_run = set(sys.argv[1:]) # Get rid of the program name
    if '--verbose' in langs_to_run:
        VERBOSE = True
        langs_to_run.remove('--verbose')
    if len(langs_to_run) == 0:
        langs_to_run = set([testcase['language_id'] for testcase in TEST_SET])
    counters = [0, 0, 0]  # Passes, fails, exceptions
    tests_run = 0;
    for test in TEST_SET:
        if test['language_id'] in langs_to_run:
            tests_run += 1
            result = run_test(test)
            counters[result] += 1
            if VERBOSE:
                print('=====================================')

    print()
    print("{} tests, {} passed, {} failed, {} exceptions".format(
        tests_run, counters[0], counters[1], counters[2]))

    if 'c' in langs_to_run:
        check_parallel_submissions()
        check_bad_cputime()

    return counters[1] + counters[2]


sys.exit(main())


