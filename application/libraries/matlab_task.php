<?php defined('BASEPATH') OR exit('No direct script access allowed');

/* ==============================================================
 *
 * Matlab
 *
 * ==============================================================
 *
 * @copyright  2014, 2015 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('application/libraries/LanguageTask.php');

class Matlab_Task extends Task {
    public function __construct($filename, $input, $params) {
        parent::__construct($filename, $input, $params);
        $this->default_params['interpreterargs'] = array(
            '-nojvm',  //  don't load the Java VM
            '-r'       //  script filename follows
        );
    }

    public static function getVersionCommand() {
        return array('/usr/local/bin/matlab_exec_cli -nodisplay -nojvm -nosplash -r exit', '/\(([0-9.]*)\)/');
    }


    public function compile() {
        $this->setPath();
        $filename = basename($this->sourceFileName); // Strip any path bits
        $dotpos = strpos($filename, '.');
        if ($dotpos !== FALSE) { // Strip trailing .matlab if given
            $filename = substr($filename, 0, $dotpos);
        }
        $this->executableFileName =  $filename; // Matlab's idea of the executable filename doesn't include .m
        if (!copy($this->sourceFileName, $this->executableFileName . '.m')) {
            throw new exception("Matlab_Task: couldn't copy source file");
        }
    }

    // A default name for Matlab programs
    public function defaultFileName($sourcecode) {
        return 'prog.m';
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
        $endOfHeader = 'Research and commercial use is prohibited.';

        foreach ($lines as $line) {
            $line = rtrim($line);
            if ($headerEnded) {
                $outlines[] = $line;
            } else if (strpos($line, 'R2016b') !== FALSE) {
                // For R2016b, need a different end-of-header line
                $endOfHeader = 'Classroom License -- for classroom instructional use only.';
            } else if (strpos($line, $endOfHeader) !== FALSE) {
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


    // The Matlab CLI program is the executable
    public function getExecutablePath() {
        return '/usr/local/bin/matlab_exec_cli';
    }


    // The target file is the matlab file without its extension
    public function getTargetFile() {
        return $this->executableFileName;
    }
};

