<?php defined('BASEPATH') OR exit('No direct script access allowed');

/* ==============================================================
 *
 * Octave
 *
 * ==============================================================
 *
 * @copyright  2014 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('application/libraries/LanguageTask.php');

class Octave_Task extends Task {
    public function __construct($filename, $input, $params) {
        parent::__construct($filename, $input, $params);
        $this->default_params['interpreterargs'] = array(
             '--norc',
             '--no-window-system',
             '--silent',
             '-H');
    }

    public static function getVersionCommand() {
        return array('octave --version --norc --no-window-system --silent', '/GNU Octave, version ([0-9._]*)/');
    }

    public function compile() {
        $this->executableFileName = $this->sourceFileName . '.m';
        if (!copy($this->sourceFileName, $this->executableFileName)) {
            throw new exception("Octave_Task: couldn't copy source file");
        }
    }

    // A default name for Octave programs
    public function defaultFileName($sourcecode) {
        return 'prog.m';
    }


    public function getExecutablePath() {
         return '/usr/bin/octave';
     }


     public function getTargetFile() {
         return $this->sourceFileName;
     }


     // Remove return chars and delete the extraneous error: lines at the end
     public function filteredStderr() {
         $out1 = str_replace("\r", '', $this->stderr);
         $lines = explode("\n", $out1);
         while (count($lines) > 0 && trim($lines[count($lines) - 1]) === '') {
             array_pop($lines);
         }
         if (count($lines) > 0 &&
                 strpos($lines[count($lines) - 1],
                         'error: ignoring octave_execution_exception') === 0) {
             array_pop($lines);
         }

         // A bug in octave results in some errors lines at the end due to the
         // non-existence of some environment variables that we can't set up
         // in jobe. So trim them off.
         if (count($lines) >= 1 &&
                    $lines[count($lines) - 1] == 'error: No such file or directory') {
             array_pop($lines);
         }

         if (count($lines) > 0) {
            return implode("\n", $lines) . "\n";
         } else {
             return '';
         }
     }
}
