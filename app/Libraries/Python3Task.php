<?php

/* ==============================================================
 *
 * Python3
 *
 * ==============================================================
 *
 * @copyright  2014, 2020 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace Jobe;

class Python3Task extends LanguageTask
{

    // Raise the memory limit for python to allow for numpy, matplolib
    // etc. Set the interpreter args to ingore all Python
    // environment variables and to suppress writing of .pyc files
    // on import.
    public function __construct($filename, $input, $params)
    {
        parent::__construct($filename, $input, $params);
        $this->default_params['memorylimit'] = 1000; // NumpPy+matplotlib is getting greedier.
        $this->default_params['interpreterargs'] = array('-BE');
    }

    public static function getVersionCommand()
    {
        $python = config('Jobe')->python3_version;
        if (!file_exists($python)) {
            $python = '/usr/bin/' . $python;
        }
        return array("$python --version", '/Python ([0-9._]*)/');
    }

    public function compile()
    {
        $python = Python3Task::pythonExecutable();
        $cmd =  "$python -m py_compile {$this->sourceFileName}";
        $this->executableFileName = $this->sourceFileName;
        list($output, $this->cmpinfo) = $this->runInSandbox($cmd);
        if (!empty($this->cmpinfo) && !empty($output)) {
            $this->cmpinfo = $output . '\n' . $this->cmpinfo;
        }
    }


    // A default name for Python3 programs
    public function defaultFileName($sourcecode)
    {
        return 'prog.py';
    }


    public function getExecutablePath()
    {
        return Python3Task::pythonExecutable();
    }


    public function getTargetFile()
    {
        return $this->sourceFileName;
    }

    private static function pythonExecutable()
    {
        $python = config('Jobe')->python3_version;
        if (!file_exists($python)) {
            $python = '/usr/bin/' . $python;
        }
        return $python;
    }

}
