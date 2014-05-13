<?php

/* ==============================================================
 *
 * This file contains all the Language definitions, including
 * how to compile and run each one.
 *
 * ==============================================================
 *
 * @copyright  2014 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// A Task encapsulates the language specific behaviour associated
// with compiling and running a particular bit of source code. It is subclassed
// for each provided language.

// require_once('application/controllers/files.php');

class ResultObject {
    
    public function __construct(
            $run_id,
            $outcome,
            $cmpinfo='',
            $stdout='',
            $stderr='')
    {
        $this->run_id = $run_id;   // A unique identifying string
        $this->outcome = $outcome; // Outcome of this job
        $this->cmpinfo = $cmpinfo;
        $this->stdout = $stdout;
        $this->stderr = $stderr;
    }
}

abstract class Task {

    // Symbolic constants as per ideone API

    const RESULT_COMPILATION_ERROR = 11;
    const RESULT_RUNTIME_ERROR = 12;
    const RESULT_TIME_LIMIT   = 13;
    const RESULT_SUCCESS      = 15;
    const RESULT_MEMORY_LIMIT    = 17;
    const RESULT_ILLEGAL_SYSCALL = 19;
    const RESULT_INTERNAL_ERR = 20;

    public $DEFAULT_PARAMS = array(
        'disklimit'     => 100,     // MB
        'cputime'       => 5,       // secs
        'memorylimit'   => 50,      // MB
        'numprocs'      => 20
    );

    public $cmpinfo = '';   // Output from compilation
    public $time = 0;       // Execution time (secs)
    public $memory = 0;     // Memory used (MB)
    public $signal = 0;
    public $stdout = '';    // Output from execution
    public $stderr = '';
    public $result = Task::RESULT_INTERNAL_ERR;  // Should get overwritten
    public $workdir = '';   // The temporary working directory created in constructor

    // For all languages it is necessary to store the source code in a
    // temporary file when constructing the task. A temporary directory
    // is made to hold the source code. The standard input ($input) and
    // the run parameters ($params) are
    // saved for use at runtime.
    public function __construct($sourceCode, $filename, $input, $params) {
        $this->workdir = tempnam("/tmp", "jobe_");
        if (!unlink($this->workdir) || !mkdir($this->workdir)) {
            throw new coding_exception("Task: error making temp directory (race error?)");
        }
        $this->input = $input;
        $this->sourceFileName = $filename;
        $this->params = $params;
        chdir($this->workdir);
        $handle = fopen($this->sourceFileName, "w");
        fwrite($handle, $sourceCode);
        fclose($handle);
    }


    protected function getParam($key) {
        if (isset($this->params) && array_key_exists($key, $this->params)) {
            return $this->params[$key];
        } else {
            return $this->DEFAULT_PARAMS[$key];
        }
    }
    

    // Return the JobeAPI result object to describe the state of this task
    public function resultObject() {
        if ($this->cmpinfo) {
            $this->result = Task::RESULT_COMPILATION_ERROR;
        }
        return new ResultObject(
            $this->workdir,     // TODO get a better ID than this
            $this->result,
            $this->cmpinfo,
            $this->filteredStdout(),
            $this->filteredStderr()
        );
    }

    
    // Load the specified files into the working directory.
    // The file list is an array of (fileId, filename) pairs.
    // Return False if any are not present.
    public function load_files($fileList, $filecachedir) {
        foreach ($fileList as $file) {
            $fileId = $file[0];
            $filename = $file[1];
            $path = $filecachedir . $fileId;
            $destPath = $this->workdir . '/' . $filename;
            if (!file_exists($path) || 
               ($contents = file_get_contents($path)) === FALSE ||
               (file_put_contents($destPath, $contents)) === FALSE) {
                return FALSE;
            }
        }
        return TRUE;
    }

    // Compile the current source file in the current directory, saving
    // the compiled output in a file $this->executableFileName.
    // Sets $this->cmpinfo accordingly.
    public abstract function compile();

    

    // Execute this task, which must already have been compiled if necessary
    public function execute() {
        $filesize = 1000 * $this->getParam('disklimit'); // MB -> kB
        $memsize = 1000 * $this->getParam('memorylimit');
        $cputime = $this->getParam('cputime');
        $numProcs = $this->getParam('numprocs');
        $sandboxCmdBits = array(
             dirname(__FILE__)  . "/../../runguard/runguard",
             "--user=jobe",
             "--time=$cputime",         // Seconds of execution time allowed
             "--filesize=$filesize",    // Max file sizes
             "--nproc=$numProcs",       // Max num processes/threads for this *user*
             "--no-core",
             "--streamsize=$filesize");  // Max stdout/stderr sizes

        if ($memsize != 0) {  // Special case: Matlab won't run with a memsize set. TODO: WHY NOT!
            $sandboxCmdBits[] = "--memsize=$memsize";
        }
        $allCmdBits = array_merge($sandboxCmdBits, $this->getRunCommand());
        $cmd = implode(' ', $allCmdBits) . " >prog.out 2>prog.err";

        $workdir = $this->workdir;
        chdir($workdir);

        try {
            $this->cmpinfo = ''; // Set defaults first
            $this->signal = 0;
            $this->time = 0;
            $this->memory = 0;

            if ($this->input != '') {
                $f = fopen('prog.in', 'w');
                fwrite($f, $this->input);
                fclose($f);
                $cmd .= " <prog.in";
            }
            else {
                $cmd .= " </dev/null";
            }

            $handle = popen($cmd, 'r');
            $result = fread($handle, MAX_READ);
            pclose($handle);
            
            $this->stdout = file_get_contents("$workdir/prog.out");
            
            if (file_exists("$workdir/prog.err")) {
                $this->stderr = file_get_contents("$workdir/prog.err");
            } else {
                $this->stderr = '';
            }
            
            $this->diagnose_result();  // Analyse output and set result
        }
        catch (Exception $e) {
            $this->result = Task::RESULT_INTERNAL_ERR;
            $this->stderr = $this->cmpinfo = print_r($e, true);
            $this->stdout = $this->stderr;
            $this->signal = $this->time = $this->memory = 0;
        }
    }

    // Return the Linux command to use to run the current job with the given
    // standard input. It's an array of string arguments, suitable
    // for passing to the LiuSandbox.
    public abstract function getRunCommand();


    // Return the version of language supported by this particular Language/Task
    public abstract function getVersion();

    
    // Called after each run to set the task result value. Default is to
    // set the result to SUCCESS if there's no stderr output or to timelimit
    // exceeded if the appropriate warning message is found in stdout or
    // to runtime error otherwise.
    // Note that Runguard does not identify memorylimit exceeded as a special
    // type of runtime error so that value is not returned by default.
    
    // Subclasses may wish to add further postprocessing, e.g. for memory
    // limit exceeded if the language identifies this specifically.
    public function diagnose_result() {
        if (strlen($this->filteredStderr())) {
            $this->result = TASK::RESULT_RUNTIME_ERROR;
        } else {
            $this->result = TASK::RESULT_SUCCESS;
        } 
        
        // Refine RuntimeError if possible

        if (strpos($this->stderr, "warning: timelimit exceeded")) {
            $this->result = Task::RESULT_TIME_LIMIT;
            $this->signal = 9;
            $this->stderr = '';
        } else if(strpos($this->stderr, "warning: command terminated with signal 11")) {
            $this->signal = 11;
            $this->stderr = '';
        }
    }

    
    // Override the following function if the output from executing a program
    // in this language needs post-filtering to remove stuff like
    // header output.
    public function filteredStdout() {
        return $this->stdout;
    }


    // Override the following function if the stderr from executing a program
    // in this language needs post-filtering to remove stuff like
    // backspaces and bells.
    public function filteredStderr() {
        return $this->stderr;
    }


    // Called to clean up task when done
    public function close($deleteFiles=TRUE) {
        if ($deleteFiles) {
            $this->delTree($this->workdir);
        }
    }

    // Check if PHP exec environment includes a PATH. If not, set up a
    // default, or gcc misbehaves. [Thanks to Binoj D for this bug fix,
    // needed on his CentOS system.]
    protected function setPath() {
        $envVars = array();
        exec('printenv', $envVars);
        $hasPath = FALSE;
        foreach ($envVars as $var) {
            if (strpos($var, 'PATH=') === 0) {
                $hasPath = TRUE;
                break;
            }
        }
        if (!$hasPath) {
            putenv("PATH=/sbin:/bin:/usr/sbin:/usr/bin");
        }
    }


    // Delete a given directory tree
    private function delTree($dir) {
        $files = array_diff(scandir($dir), array('.','..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->delTree("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }
}


// =======================================================================
//
// Define the behaviour of all the supported languages
//
// =======================================================================

class Matlab_Task extends Task {
    public function __construct($source, $filename, $input, $params) {
        Task::__construct($source, $filename, $input, $params);
    }

    public function getVersion() {
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

// =======================================================================
class Octave_Task extends Task {
    public function __construct($source, $filename, $input, $params) {
        Task::__construct($source, $filename, $input, $params);
    }

    public function getVersion() {
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

// =======================================================================
class Python2_Task extends Task {
    public function __construct($source, $filename, $input, $params) {
        Task::__construct($source, $filename, $input, $params);
    }

    public function getVersion() {
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

// =======================================================================
class Python3_Task extends Task {
    public function __construct($source, $filename, $input, $params) {
        Task::__construct($source, $filename, $input, $params);
    }

    public function getVersion() {
        return 'Python 3.2';
    }

    public function compile() {
        $outputLines = array();
        $returnVar = 0;
        exec("python3 -m py_compile {$this->sourceFileName} 2>compile.out", 
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
             '/usr/bin/python3',
             '-BE',
             $this->sourceFileName
         );
     }
};

// =======================================================================
class Java_Task extends Task {
    public function __construct($source, $filename, $input, $params) {
        Task::__construct($source, $filename, $input, $params);
        
        // TODO: find out why java won't work with memory limit set to
        // more plausible values.
        $this->params = array('memorylimit' => 0);
    }

    public function getVersion() {
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
            exec("/usr/bin/javac {$this->sourceFileName} 2>compile.out", $output, $returnVar);
            if ($returnVar == 0) {
                $this->cmpinfo = '';
                $this->executableFileName = $this->sourceFileName;
            }
            else {
                $this->cmpinfo = file_get_contents('compile.out');
            }
        }
    }


    public function getRunCommand() {
        return array(
             '/usr/bin/java',
             "-Xrs",   //  reduces usage signals by java, because that generates debug
                       //  output when program is terminated on timelimit exceeded.
             "-Xss8m",
             "-Xmx200m",
             $this->mainClassName
         );
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
};

// =======================================================================
class C_Task extends Task {

    public function __construct($source, $filename, $input, $params) {
        Task::__construct($source, $filename, $input, $params);
    }

    public function getVersion() {
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

?>
