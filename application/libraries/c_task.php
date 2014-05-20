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

    public function __construct($source, $filename, $input, $params) {
        Task::__construct($source, $filename, $input, $params);
    }

    public static function getVersion() {
        return 'gcc-4.6.3';
    }

    public function compile() {
        $src = basename($this->sourceFileName);
        $errorFileName = "$src.err";
        $execFileName = "$src.exe";
        $cmd = "gcc -Wall -Werror -std=c99 -x c -o $execFileName $src -lm 2>$errorFileName";
        // To support C++ instead use something like ...
        // $cmd = "g++ -Wall -Werror -x ++ -o $execFileName $src -lm 2>$errorFileName";
        exec($cmd, $output, $returnVar);
        if ($returnVar == 0) {
            $this->cmpinfo = '';
            $this->executableFileName = $execFileName;
        }
        else {
            $this->cmpinfo = file_get_contents($errorFileName);
        }
    }


    public function getRunCommand() {
        return array(
             "./" . $this->executableFileName
         );
    }
};