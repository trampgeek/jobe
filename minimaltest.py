#! /usr/bin/env python3
""" minimaltest.py - the simplest test of jobe. Runs a C program using a Jobe
    server on localhost.
"""

from urllib.error import HTTPError
import json
import http.client

JOBE_SERVER = 'localhost'

C_CODE = """
#include <stdio.h>

int main() {
    printf("Hello world\\n");
}
"""

# =============================================================

def run_test(language, code, filename):
    """Execute the given code in the given language.
       Return the result object.
    """
    runspec = {
        'language_id': language,
        'sourcefilename': filename,
        'sourcecode': code,
    }

    resource = '/jobe/index.php/restapi/runs/'
    data = json.dumps({ 'run_spec' : runspec })
    result = {}
    content = ''
    headers = {"Content-type": "application/json; charset=utf-8",
               "Accept": "application/json"}
    try:
        connect = http.client.HTTPConnection(JOBE_SERVER)
        print("POST data:\n", data)
        connect.request('POST', resource, data, headers)
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
            print(ro['stdout'])
        else:
            print("No output")
        if ro['stderr']:
            print()
            print("Error output:")
            print(ro['stderr'])


def main():
    '''Demo of a C program run'''
    print("Running C program")
    result_obj = run_test('c', C_CODE, 'test.c')
    display_result(result_obj)

main()

