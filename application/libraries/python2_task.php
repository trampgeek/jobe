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
    public function __construct($filename, $input, $params) {
        parent::__construct($filename, $input, $params);
        $this->default_params['interpreterargs'] = array('-BESs');
    }

    public static function getVersionCommand() {
        return array('python2 --version', '/Python ([0-9._]*)/');
    }

    // A default name for Python2 programs
    public function defaultFileName($sourcecode) {
        return 'prog.py2';
    }

    public function compile() {
        $this->executableFileName = $this->sourceFileName;
    }


    public function getExecutablePath() {
        return '/usr/bin/python2';
     }


     public function getTargetFile() {
         return $this->sourceFileName;
     }
};

