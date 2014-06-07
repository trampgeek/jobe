<?php defined('BASEPATH') OR exit('No direct script access allowed');

/* ==============================================================
 *
 * PHP5
 *
 * ==============================================================
 *
 * @copyright  2014 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('application/libraries/LanguageTask.php');

class Php_Task extends Task {
    public function __construct($source, $filename, $input, $params) {
        Task::__construct($source, $filename, $input, $params);
    }

    public static function getVersion() {
        return 'PHP 5.5.9-1ubuntu4';
    }

    public function compile() {
        $outputLines = array();
        $returnVar = 0;
        exec("/usr/bin/php5 -l {$this->sourceFileName} 2>compile.out", 
                $outputLines, $returnVar);
        if ($returnVar == 0) {
            $this->cmpinfo = '';
            $this->executableFileName = $this->sourceFileName;
        }
        else {
            $output = implode("\n", $outputLines);
            $compileErrs = file_get_contents('compile.out');
            if ($output) {
                $this->cmpinfo = $output . '\n' . $compileErrs;
            } else {
                $this->cmpinfo = $compileErrs;
            }
        }
    }


    // Return the command to pass to localrunner as a list of arguments,
    // starting with the program to run followed by a list of its arguments.
    public function getRunCommand() {
        return array(
             '/usr/bin/php5',
            '--no-php-ini',
             $this->executableFileName
         );
     }
};
