<?php defined('BASEPATH') OR exit('No direct script access allowed');

/* ==============================================================
 *
 * Python2
 *
 * ==============================================================
 *
 * @copyright  2014 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('application/libraries/LanguageTask.php');


class Python2_Task extends Task {
    public function __construct($source, $filename, $input, $params) {
        Task::__construct($source, $filename, $input, $params);
    }

    public static function getVersion() {
        return 'Python 2.7';
    }

    public function compile() {
        $this->executableFileName = $this->sourceFileName;
    }


    // Return the command to pass to localrunner as a list of arguments,
    // starting with the program to run followed by a list of its arguments.
    public function getRunCommand() {
        return array(
             '/usr/bin/python2',
             '-BESs',
             $this->sourceFileName
         );
     }
};

