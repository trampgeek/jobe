<?php defined('BASEPATH') OR exit('No direct script access allowed');

/* ==============================================================
 *
 * Matlab
 *
 * ==============================================================
 *
 * @copyright  2014 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('application/libraries/LanguageTask.php');

class Matlab_Task extends Task {
    public function __construct($source, $filename, $input, $params) {
        Task::__construct($source, $filename, $input, $params);
    }

    public static function getVersion() {
        return 'Matlab R2013b';
    }

    public function compile() {
        $this->setPath();
        $this->executableFileName = $this->sourceFileName . '.m';
        if (!copy($this->sourceFileName, $this->executableFileName)) {
            throw new coding_exception("Matlab_Task: couldn't copy source file");
        }
    }

    public function getRunCommand() {
         return array(
             '/usr/local/bin/matlab_exec_cli',
             '-nojvm',
             '-r',
             basename($this->sourceFileName)
         );
     }


     // Matlab throws in backspaces (grrr). There's also an extra BEL char
     // at the end of any abort error message (presumably introduced at some
     // point due to the EOF on stdin, which shuts down matlab).
     public function filteredStderr() {
         $out = '';
         for ($i = 0; $i < strlen($this->stderr); $i++) {
             $c = $this->stderr[$i];
             if ($c === "\x07") {
                 // pass
             } elseif ($c === "\x08" && strlen($out) > 0) {
                 $out = substr($out, 0, -1);
             } else {
                 $out .= $c;
             }
         }
         return $out;
     }


     public function filteredStdout() {
         $lines = explode("\n", $this->stdout);
         $outlines = array();
         $headerEnded = FALSE;

         foreach ($lines as $line) {
             $line = rtrim($line);
             if ($headerEnded) {
                 $outlines[] = $line;
             }
             if (strpos($line, 'For product information, visit www.mathworks.com.') !== FALSE) {
                 $headerEnded = TRUE;
             }
         }

         // Remove blank lines at the start and end
         while (count($outlines) > 0 && strlen($outlines[0]) == 0) {
             array_shift($outlines);
         }
         while(count($outlines) > 0 && strlen(end($outlines)) == 0) {
             array_pop($outlines);
         }

         return implode("\n", $outlines) . "\n";
     }
};

