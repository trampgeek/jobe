#! /usr/bin/env python3
''' simpletest.py - a simple demo of how to submit a program
    to Jobe. Demonstrates python3, C++ and Java.
    Includes a call to get the list of languages.
'''

from urllib.error import HTTPError
import json
import http.client

API_KEY = '2AAA7A5415B4A9B394B54BF1D2E9D'  # A working (100/hr) key on Jobe2
USE_API_KEY = True
#JOBE_SERVER = 'jobe2.cosc.canterbury.ac.nz'
JOBE_SERVER = 'localhost'

PYTHON_CODE = """
MESSAGE = 'Hello Jobe!'

def sillyFunc(message):
    '''Pointless function that prints the given message'''
    print("Message is", message)

sillyFunc(MESSAGE)
"""

CPP_CODE = """
#include <iostream>
#define MESSAGE "Hello Jobe!"
using namespace std;

int main() {
    cout << MESSAGE << endl;
}
"""


JAVA_CODE = """
public class Blah {
    public static void main(String[] args) {
        System.out.println("Farewell cruel world");
    }
}
"""

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



def run_test(language, code, filename):
    '''Execute the given code in the given language.
       Return the result object.'''
    runspec = {
        'language_id': language,
        'sourcefilename': filename,
        'sourcecode': code,
    }

    resource = '/jobe/index.php/restapi/runs/'
    data = json.dumps({ 'run_spec' : runspec })
    response = None
    content = ''
    result = do_http('POST', resource, data)
    return result


def do_http(method, resource, data=None):
    """Send the given HTTP request to Jobe, return json-decoded result as
       a dictionary (or the empty dictionary if a 204 response is given).
    """
    result = {}
    headers = {"Content-type": "application/json; charset=utf-8",
               "Accept": "application/json"}
    try:
        connect = http_request(method, resource, data, headers)
        response = connect.getresponse()
        if response.status != 204:
            content = response.read().decode('utf8')
            if content:
                result = json.loads(content)
        connect.close()

    except (HTTPError, ValueError) as e:
        print("\n***************** HTTP ERROR ******************\n")
        if response:
            print(' Response:', response.status, response.reason, content)
        else:
            print(e)
    return result


def trim(s):
    '''Return the string s limited to 10k chars'''
    MAX_LEN = 10000
    if len(s) > MAX_LEN:
        return s[:MAX_LEN] + '... [etc]'
    else:
        return s


def display_result(ro):
    '''Display the given result object'''
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
        21: 'Server overload'}

    code = ro['outcome']
    print("{}".format(outcomes[code]))
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



def main():
    '''Demo or get languages, a run of Python3 then C++ then Java'''
    print("Supported languages:")
    resource = '/jobe/index.php/restapi/languages'
    lang_versions = do_http('GET', resource)
    for lang, version in lang_versions:
        print("    {}: {}".format(lang, version))
    print()
    print("Running python...")
    result_obj = run_test('python3', PYTHON_CODE, 'test.py')
    display_result(result_obj)
    print("\n\nRunning C++")
    result_obj = run_test('cpp', CPP_CODE, 'test.cpp')
    display_result(result_obj)
    print("\n\nRunning Java")
    result_obj = run_test('java', JAVA_CODE, 'Blah.java')
    display_result(result_obj)

main()

