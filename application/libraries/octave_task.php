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
    public function __construct($source, $filename, $input, $params) {
        Task::__construct($source, $filename, $input, $params);
    }

    public static function getVersion() {
        return 'Octave 3.6.4';
    }

    public function compile() {
        $this->executableFileName = $this->sourceFileName . '.m';
        if (!copy($this->sourceFileName, $this->executableFileName)) {
            throw new coding_exception("Octave_Task: couldn't copy source file");
        }
    }

    public function getRunCommand() {
         return array(
             '/usr/bin/octave',
             '--norc',
             '--no-window-system',
             '--silent',
             basename($this->sourceFileName)
         );
     }


     // Remove return chars and delete the extraneous error: lines
     public function filteredStderr() {
         $out1 = str_replace("\r", '', $this->stderr);
         $out2 = preg_replace("/\nerror:.*\n/s", "\n", $out1);
         $out3 = preg_replace("|file /tmp/coderunner_.*|", 'source file', $out2);
         return $out3;
     }
}
