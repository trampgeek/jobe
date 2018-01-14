<?php defined('BASEPATH') OR exit('No direct script access allowed');

/* ==============================================================
 *
 * Node-js
 *
 * ==============================================================
 *
 * @copyright  2014 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('application/libraries/LanguageTask.php');

class Nodejs_Task extends Task {
    public function __construct($filename, $input, $params) {
        parent::__construct($filename, $input, $params);
        $this->default_params['interpreterargs'] = array('--use_strict');
    }

    public static function getVersionCommand() {
        return array('nodejs --version', '/v([0-9._]*)/');
    }

    public function compile() {
        $this->executableFileName = $this->sourceFileName;
        if (strpos('.js', $this->executableFileName) != strlen($this->executableFileName) - 3) {
            $this->executableFileName .= '.js';
        }
        if (!copy($this->sourceFileName, $this->executableFileName)) {
            throw new exception("Node_Task: couldn't copy source file");
        }
    }


    // A default name forjs programs
    public function defaultFileName($sourcecode) {
        return 'prog.js';
    }

    public function getExecutablePath() {
         return '/usr/bin/nodejs';
     }


     public function getTargetFile() {
         return $this->sourceFileName;
     }
}
