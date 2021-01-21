<?php defined('BASEPATH') OR exit('No direct script access allowed');

/* ==============================================================
 *
 * Python3
 *
 * ==============================================================
 *
 * @copyright  2014, 2020 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('application/libraries/LanguageTask.php');

class Python3_Task extends Task {

    // Raise the memory limit for python to allow for numpy, matplolib
    // etc. Set the interpreter args to ingore all Python
    // environment variables and to suppress writing of .pyc files
    // on import.
    public function __construct($filename, $input, $params) {
        parent::__construct($filename, $input, $params);
        $this->default_params['memorylimit'] = 600; // Need more for numpy
        $this->default_params['interpreterargs'] = array('-BE');
    }

    public static function getVersionCommand() {
        return array('python3 --version', '/Python ([0-9._]*)/');
    }

    public function compile() {
        //$cmd = "python3 -m py_compile {$this->sourceFileName}";
        // Workaround for python3 py_compile bug (https://bugs.python.org/issue38731)
        // still apparently not fixed 18 months later?
        $cmd = "python3 -c \"from py_compile import compile\ncompile('{$this->sourceFileName}')\"";
        $this->executableFileName = $this->sourceFileName;
        list($output, $this->cmpinfo) = $this->run_in_sandbox($cmd);
        if (!empty($this->cmpinfo) && !empty($output)) {
            $this->cmpinfo = $output . '\n' . $this->cmpinfo;
        }
    }


    // A default name for Python3 programs
    public function defaultFileName($sourcecode) {
        return 'prog.py';
    }


    public function getExecutablePath() {
        return '/usr/bin/python3';
     }


     public function getTargetFile() {
         return $this->sourceFileName;
     }
};
