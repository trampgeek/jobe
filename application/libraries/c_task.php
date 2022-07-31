<?php defined('BASEPATH') OR exit('No direct script access allowed');

/* ==============================================================
 *
 * C
 *
 * ==============================================================
 *
 * @copyright  2014 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('application/libraries/LanguageTask.php');

class C_Task extends Task {

    public $runnable = false;

    public function __construct($filename, $input, $params) {
        parent::__construct($filename, $input, $params);
        $this->default_params['compileargs'] = array(
            '-Wall',
            '-std=c99',
            '-x c');
    }

    public static function getVersionCommand() {
        return array('gcc --version', '/gcc \(.*\) ([0-9.]*)/');
    }

    public function isRunnable() {
      return $this->runnable;
    }

    public function compile() {
        $src = basename($this->sourceFileName);
        $this->executableFileName = $execFileName = "$src.exe";
        $compileargs = $this->getParam('compileargs');
        $linkargs = $this->getParam('linkargs');
        $cmd = "gcc " . implode(' ', $compileargs) . " -o $execFileName $src " . implode(' ', $linkargs);
        if (file_exists($this->getExecutablePath())) {
          unlink($this->getExecutablePath());
          log_message("error", "Runnable should not exist before compilation!");
        }
        $this->runnable = false;
        list($output, $this->cmpinfo) = $this->run_in_sandbox($cmd);
        if (file_exists($this->getExecutablePath())) {
          $this->runnable = true;
        }
    }

    // A default name for C programs
    public function defaultFileName($sourcecode) {
        return 'prog.c';
    }


    // The executable is the output from the compilation
    public function getExecutablePath() {
        return "./" . $this->executableFileName;
    }


    public function getTargetFile() {
        return '';
    }
};
