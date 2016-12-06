<?php defined('BASEPATH') OR exit('No direct script access allowed');

/* ==============================================================
 *
 * Pascal
 *
 * ==============================================================
 *
 * @copyright  2015 Fedor Lyanguzov, based on 2014 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('application/libraries/LanguageTask.php');

class Pascal_Task extends Task {

    public function __construct($source, $filename, $input, $params) {
        Task::__construct($source, $filename, $input, $params);
        $this->default_params['compileargs'] = array(
            '-vew', // [v]erbose, [e]rrors, [w]arnings
            '-Se'); // stop on first error
    }

    public static function getVersion() {
        return 'fpc-2.6.0'; // maybe something like 'fpc-' . exec('fpc -iV') to get correct version?
    }

    public function compile() {
        $src = basename($this->sourceFileName);
        $errorFileName = "$src.err";
        $execFileName = "$src.exe";
        $compileargs = $this->getParam('compileargs');
//        $cmd = "gcc " . implode(' ', $compileargs) . " -o $execFileName $src -lm 2>$errorFileName";
        $cmd = "fpc " . implode(' ', $compileargs) . " -Fe$errorFileName -o$execFileName $src";
	// -Fe[filename] - store error log in file
        exec($cmd, $output, $returnVar);
        if ($returnVar == 0) {
            $this->cmpinfo = '';
            $this->executableFileName = $execFileName;
        }
        else {
            $this->cmpinfo = file_get_contents($errorFileName);
        }
    }

    // A default name for Pascal programs
    public function defaultFileName($sourcecode) {
        return 'prog.pas';
    }
    
    
    // The executable is the output from the compilation
    public function getExecutablePath() {
        return "./" . $this->executableFileName;
    }
    
    
    public function getTargetFile() {
        return '';
    }
};
