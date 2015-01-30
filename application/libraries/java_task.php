<?php defined('BASEPATH') OR exit('No direct script access allowed');

/* ==============================================================
 *
 * Octabe
 *
 * ==============================================================
 *
 * @copyright  2014 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('application/libraries/LanguageTask.php');

class Java_Task extends Task {
    public function __construct($source, $filename, $input, $params) {
        // TODO: find out why java won't work with memory limit set to
        // more plausible values.
        $params['memorylimit'] = 0;
        Task::__construct($source, $filename, $input, $params);
        $this->default_params['interpreterargs'] = array(
             "-Xrs",   //  reduces usage signals by java, because that generates debug
                       //  output when program is terminated on timelimit exceeded.
             "-Xss8m",
             "-Xmx200m"
        );
    }

    public static function getVersion() {
        return 'Java 1.7';
    }

    public function compile() {
        $prog = file_get_contents($this->sourceFileName);
        if (($this->mainClassName = $this->getMainClass($prog)) === FALSE) {
            $this->cmpinfo = "Error: no main class found, or multiple main classes. [Did you write a public class when asked for a non-public one?]";
        }
        else {
            exec("mv {$this->sourceFileName} {$this->mainClassName}.java", $output, $returnVar);
            if ($returnVar !== 0) {
                throw new coding_exception("Java compile: couldn't rename source file");
            }
            $this->sourceFileName = "{$this->mainClassName}.java";
            $compileArgs = $this->getParam('compileargs');
            $cmd = '/usr/bin/javac ' . implode(' ', $compileArgs) . " {$this->sourceFileName} 2>compile.out";
            exec($cmd, $output, $returnVar);
            if ($returnVar == 0) {
                $this->cmpinfo = '';
                $this->executableFileName = $this->sourceFileName;
            }
            else {
                $this->cmpinfo = file_get_contents('compile.out');
            }
        }
    }


    public function getExecutablePath() {
        return '/usr/bin/java';
    }
    
    
     
     public function getTargetFile() {
         return $this->mainClassName;
     }


     // Return the name of the main class in the given prog, or FALSE if no
     // such class found. Uses a regular expression to find a public class with
     // a public static void main method.
     // Not totally safe as it doesn't parse the file, e.g. would be fooled
     // by a commented-out main class with a different name.
     private function getMainClass($prog) {
         $pattern = '/(^|\W)public\s+class\s+(\w+)\s*\{.*?public\s+static\s+void\s+main\s*\(\s*String/ms';
         if (preg_match_all($pattern, $prog, $matches) !== 1) {
             return FALSE;
         }
         else {
             return $matches[2][0];
         }
     }
     
     // Get rid of the tab characters at the start of indented lines in 
     // traceback output.
     public function filteredStderr() {
         return str_replace("\n\t", "\n        ", $this->stderr);
     }
};

