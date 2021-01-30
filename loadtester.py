#! /usr/bin/env python3
# coding=utf-8
''' A jobe load tester. Run with, e.g. 'python3 loadtester.py 10 [python3|c]'
    argv[1] is the initial number of parallel submissions to try. If all succeed
    the number is doubled and the entire run is re-tried (after a 5 second delay).
    This continues until 1 or more submissions fail.
    If argv[2] isn't specified, all test programs (currently just one Python
    and one C) are tried. Otherwise only test programs in the specified language
    are run.
    
    Richard Lobb
    12/12/2020
'''

from urllib.request import urlopen
from urllib.parse import urlencode
from urllib.error import HTTPError
import json
import sys
import http.client
from threading import Thread, Lock
from time import perf_counter, sleep
import copy


# Get more output 
DEBUGGING = False

# Set JOBE_SERVER to the Jobe server URL.
# If Jobe expects an X-API-Key header, set API_KEY to a working value and set
# USE_API_KEY to True.
API_KEY = '2AAA7A5415B4A9B394B54BF1D2E9D'  # A working (100/hr) key on Jobe2

USE_API_KEY = True
JOBE_SERVER = 'localhost'
RUNS_RESOURCE = '/jobe/index.php/restapi/runs/'

#JOBE_SERVER = 'csse-jobe5.canterbury.ac.nz'

GOOD_TEST = 0
FAIL_TEST = 1
EXCEPTION = 2


# ===============================================
#
# Test List
#
# ===============================================

TEST_SET = [


{
    'comment': 'Python3 template-preprocessor example',
    'language_id': 'python3',
    'sourcecode': r'''
import random, json, sys

params = dict([param.split("=") for param in sys.argv[1:]])
random.seed(params['seed'])

animals = [('Dog', 'Woof')]
animal = animals[random.randrange(0, len(animals))]
print(json.dumps({
    'animal': {'name': animal[0], 'sound': animal[1]}}))
''',
    'sourcefilename': 'test.py',
    'expect': { 'outcome': 15, 'stdout': '{"animal": {"name": "Dog", "sound": "Woof"}}\n' },
    'parameters': {'runargs': ['seed=10'] }
},

{
        'comment': 'C hello world example',
        'language_id': 'c',
        'sourcecode': r'''#include <stdio.h>
#include <unistd.h>
int main() {
    printf("Hello 1\n");
    printf("Hello 2\n");
}''',
        'sourcefilename': 'test.c',
        'expect': { 'outcome': 15, 'stdout': 'Hello 1\nHello 2\n' }
    }

]

#==========================================================================
#
# Now the tester code
#
#==========================================================================

# Global outcome variables
successes = 0
fails = 0

def check_parallel_submissions(job, num_parallel_submits):
    '''Check that we can submit several jobs at once to Jobe with
       the process limit set to 1 and still have no conflicts.
    '''
    threads = []

    lock = Lock()
    t0 = perf_counter()
    for child_num in range(num_parallel_submits):
        if DEBUGGING:
            print("Doing child", child_num)
        def run_job():
            global successes, fails
            this_job = copy.deepcopy(job)
            this_job['comment'] += '. Child' + str(child_num)
            status, result = run_test(job)
            lock.acquire()
            if status == GOOD_TEST:
                successes += 1
            else:
                fails += 1
                print(result)
            lock.release()

        t = Thread(target=run_job)
        threads.append(t)
        t.start()

    for t in threads:
        t.join()
    t1 = perf_counter()
    print(f"{num_parallel_submits} done in {t1 - t0:.2f} secs, {successes} successes, {fails} fails")



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
            error_message = f"******** Put file/check file failed ({response_code}). File not found.****"
            if DEBUGGING:
                print(error_message)
            return EXCEPTION, error_message

    # Prepare the request

    data = json.dumps({ 'run_spec' : runspec })
    response = None
    content = ''

    # Do the request, returning EXCEPTION if it broke
    ok, result = do_http('POST', RUNS_RESOURCE, data)
    if not ok:
        return EXCEPTION, string_result(result)

   # If not an exception, check the response is as specified

    if is_correct_result(test['expect'], result):
        if DEBUGGING:
            print(test['comment'], string_result(result))
        return GOOD_TEST, ''
    else:
        if DEBUGGING:
            print("\n***************** FAILED TEST ******************\n")
            print(result)
            display_result(test['comment'], result)
            print("\n************************************************\n")
        return FAIL_TEST, string_result(result)


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
        result = str(e)
        if DEBUGGING:
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


def string_result(ro):
    '''Convert the given result object to a string'''
    if not isinstance(ro, dict) or 'outcome' not in ro:
        return f"Bad result object {ro}"

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
    string = f"Jobe result: {outcomes[code]}"

    if ro['cmpinfo']:
        string += f"\nCompiler output:\n{ro['cmpinfo']}"
    else:
        if ro['stdout']:
            string += f"Output:\n{trim(ro['stdout'])}"
        if ro['stderr']:
            string += f"\nError output:\ntrim(ro['stderr'])"
    return string


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



def main():
    '''Run with optional argument specifying language to test. If omitted, test all.'''
    lang = None
    if len(sys.argv) < 2:
        print('Usage: loadtester initial_num_runs [language]')
        sys.exit(0)
    initial_num_submits = int(sys.argv[1])
    if len(sys.argv) > 2:
        lang = sys.argv[2].lower()
    do_get_languages()

    for test in TEST_SET:
        global successes, fails
        if lang is not None and test['language_id'] != lang:
            continue
        fails = 0
        print(f"Load-testing with task {test['comment']}")
    
        num_submits = initial_num_submits
        while fails == 0: 
            successes = 0
            check_parallel_submissions(test, num_submits)
            num_submits *= 2
            sleep(5)

sys.exit(main())
