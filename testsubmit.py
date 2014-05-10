from urllib.request import urlopen
from urllib.parse import urlencode
from urllib.error import HTTPError
import json
import http.client
from base64 import b64encode

# ===============================================
#
# Test List
#
# ===============================================

VERBOSE = True
DEBUGGING = False

GOOD_TEST = 0
FAIL_TEST = 1
EXCEPTION = 2

TEST_SET = [
{
    'comment': 'Valid Python3',
    'language_id': 'python3',
    'sourcecode': r'''print("Hello world!")
''',
    'sourcefilename': 'test.py',
    'expect': { 'outcome': 0, 'stdout': 'Hello world!\n' }
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
    'comment': 'Test good C hello world',
    'language_id': 'c',
    'sourcecode': r'''#include <stdio.h>
int main() {
    printf("Hello world\nIsn't this fun!\n");
}
''',
    'sourcefilename': 'prog.c',
    'expect': { 'outcome': 0, 'stdout': "Hello world\nIsn't this fun!\n" }
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

#{
    #'comment': 'Test timelimit on C',
    #'language_id': 'c',
    #'sourcecode': r'''#include <stdio.h>
#int main() {
    #while(1) {};
#}
#''',
    #'sourcefilename': 'prog.c',
    #'expect': { 'outcome': 13 }
#},

{
    'comment': 'Memory limit exceeded in C (seg faults)',
    'language_id': 'c',
    'sourcecode': r'''#include <stdio.h>
#include <assert.h>
int main() {
    int data[1000000000];
    printf("%ld\n", sizeof(data));
}
''',
    'sourcefilename': 'prog.c',
    'expect': { 'outcome': 12 }
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
    'expect': { 'outcome': 0, 'stdout': '''The first file
Line 2
Second file
'''}
}
]


def is_correct_result(expected, got):
    '''True iff every key in the expected outcome exists in the
       actual outcome and the associated values are equal, too'''
    for key in expected:
        if key not in got or expected[key] != got[key]:
            return False
    return True

# =============================================================

def check_file(file_id):
    '''Checks if the given fileid exists on the server.
       Returns status: 200 denotes file exists, 404 denotes file not found.
    '''

    resource = '/jobe/index.php/restapi/files/' + file_id
    headers = {"Accept": "text/plain"}
    connect = http.client.HTTPConnection('localhost')
    connect.request('HEAD', resource, '', headers)
    try:
        response = connect.getresponse()
    except HTTPError: pass

    if VERBOSE:
        print("Response to getting status of file ", file_id, ':')
        content = ''
        if response.status != 204:
            content =  response.read(4096)
        print(response.status, response.reason, content)

    connect.close()
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
    connect = http.client.HTTPConnection('localhost')
    connect.request('PUT', resource, data, headers)
    response = connect.getresponse()
    if VERBOSE:
        print("Response to putting", file_id, ':')
        content = ''
        if response.status != 204:
            content =  response.read(4096)
        print(response.status, response.reason, content)

    connect.close()


def run_test(test):
    '''Execute the given test, checking the output'''
    runspec = {}
    for key in test:
        if key not in ['comment', 'expect', 'files']:
            runspec[key] = test[key]
    if DEBUGGING:
        runspec['debug'] = True

    # First put any files to the server
    for file_desc in test.get('files', []):
        put_file(file_desc)
        if check_file(file_desc[0]) != 200:
            print("******** Put file/check file failed. File not found.****")

    # Prepare the request

    # url = 'http://192.168.1.107/jobe/index.php/restapi/runs'
    url = 'http://localhost/jobe/index.php/restapi/runs'
    resource = '/jobe/index.php/restapi/runs/'
    data = json.dumps({ 'run_spec' : runspec })
    headers = {"Content-type": "application/json",
               "Accept": "text/plain"}
    response = None
    content = ''
    result = {}
    # Send the request
    try:
        connect = http.client.HTTPConnection('localhost')
        connect.request('POST', resource, data, headers)
        response = connect.getresponse()
        if response.status != 204:
            content = response.read().decode('utf8')
            if content:
                result = json.loads(content)
        connect.close()

    except (HTTPError, ValueError) as e:
        print("\n***************** HTTP ERROR ******************\n")
        print(test['comment'], end='')
        if response:
            print(' Response:', response.code, response.reason, content)
        else:
            print(e)
        return EXCEPTION

   # Lastly, check the response is as specified

    if is_correct_result(test['expect'], result):
        if VERBOSE:
            display_result(test['comment'], result)
        else:
            print(test['comment'] + ' OK')
        return GOOD_TEST
    else:
        print("\n***************** FAILED TEST ******************\n")
        display_result(test['comment'], result)
        print("\n************************************************\n")
        return FAIL_TEST

def display_result(comment, ro):
    '''Display the given result object'''
    print(comment)
    if isinstance(ro, str) or isinstance(ro, list):
        print(ro)
        return

    outcomes = {
        0:  'Successful run',
        11: 'Compile error',
        12: 'Runtime error',
        13: 'Time limit exceeded',
        15: 'Successful run',
        17: 'Memory limit exceeded',
        19: 'Illegal system call',
        20: 'Internal error, please report'}

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
    '''Every home should have one'''
    counters = [0, 0, 0]  # Passes, fails, exceptions
    for test in TEST_SET:
        result = run_test(test)
        counters[result] += 1
        if VERBOSE:
            print('=====================================')

    print()
    print("{} tests, {} passed, {} failed, {} exceptions".format(
        len(TEST_SET), counters[0], counters[1], counters[2]))




main()
