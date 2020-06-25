<?php defined('BASEPATH') OR exit('No direct script access allowed');

/* ==============================================================
 *
 * VHDL
 *
 * ==============================================================
 *
 * @copyright  2020 Clément Leboulenger, based on 2014 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('application/libraries/LanguageTask.php');

class VHDL_Task extends Task {

    public function __construct($filename, $input, $params) {
        parent::__construct($filename, $input, $params);
        $this->default_params['compileargs'] = array(
            // '-fsynopsys', // The use of non standard library will not produce an error -> Not recognized on command line
            '--ieee=standard',
            '--mb-comments', // Allow UTF8 or multi-bytes chars in a comment.
			'-C', // See above
			'-fno-caret-diagnostics' // Remove source line of error
            );
    }

    public static function getVersionCommand() {
        return array('ghdl-gcc --version', '/GHDL ([0-9.]*)/');
    }

    public function compile() {
        $src = basename($this->sourceFileName);
        $this->executableFileName = $execFileName = "$src.exe";
        $compileargs = $this->getParam('compileargs');
        $cmd = "ghdl-gcc " . "-c " . implode(' ', $compileargs) . " -o $execFileName $src " . "-e test_bench" ; // The top entity must be named test_bench
        list($output, $this->cmpinfo) = $this->run_in_sandbox($cmd);
    }

    // A default name for VHDL programs
    public function defaultFileName($sourcecode) {
        return 'prog.vhd';
    }


    // The executable is the output from the compilation
    public function getExecutablePath() {
        return "./" . $this->executableFileName;
    }


    public function getTargetFile() {
        return '';
    }
};
