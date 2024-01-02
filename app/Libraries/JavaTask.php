<?php

/* ==============================================================
 *
 * Java
 *
 * ==============================================================
 *
 * @copyright  2014 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace Jobe;

class JavaTask extends Task
{
    public string $mainClassName;
    
    public function __construct($filename, $input, $params)
    {
        $params['memorylimit'] = 0;    // Disregard memory limit - let JVM manage memory
        $this->default_params['numprocs'] = 256;     // Java 8 wants lots of processes
        $this->default_params['interpreterargs'] = array(
             "-Xrs",   //  reduces usage signals by java, because that generates debug
                       //  output when program is terminated on timelimit exceeded.
             "-Xss8m",
             "-Xmx200m"
        );
        $this->default_params['main_class'] = null;

        // Extra global Java arguments
        if (config('Jobe')->java_extraflags != '') {
            array_push($this->default_params['interpreterargs'], config('Jobe')->java_extraflags);
        }

        if (isset($params['numprocs']) && $params['numprocs'] < 256) {
            $params['numprocs'] = 256;  // Minimum for Java 8 JVM
        }

        parent::__construct($filename, $input, $params);
    }

    public function prepareExecutionEnvironment($sourceCode)
    {
        parent::prepareExecutionEnvironment($sourceCode);

        // Superclass calls subclasses to get filename if it's
        // not provided, so $this->sourceFileName should now be set correctly.
        $extStart = strpos($this->sourceFileName, '.');  // Start of extension
        $this->mainClassName = substr($this->sourceFileName, 0, $extStart);
    }

    public static function getVersionCommand()
    {
        return array('java -version', '/version "?([0-9._]*)/');
    }

    public function compile()
    {
        // Extra global Javac arguments
        $extra_javacflags = config('Jobe')->javac_extraflags;

        $prog = file_get_contents($this->sourceFileName);
        $compileArgs = $this->getParam('compileargs');
        $cmd = '/usr/bin/javac ' . $extra_javacflags . ' ' . implode(' ', $compileArgs) . " {$this->sourceFileName}";
        list($output, $this->cmpinfo) = $this->runInSandbox($cmd);
        if (empty($this->cmpinfo)) {
            $this->executableFileName = $this->sourceFileName;
        }
    }

    // A default name for Java programs. [Called only if API-call does
    // not provide a filename. As a side effect, also set the mainClassName.
    public function defaultFileName($sourcecode)
    {
        $main = $this->getMainClass($sourcecode);
        if ($main === false) {
            $this->cmpinfo .= "WARNING: can't determine main class, so source file has been named 'prog.java', " .
                "which probably won't compile.";
            return 'prog.java'; // This will probably fail
        } else {
            return $main.'.java';
        }
    }

    public function getExecutablePath()
    {
        return '/usr/bin/java';
    }



    public function getTargetFile()
    {
        return $this->getParam('main_class') ?? $this->mainClassName;
    }


    // Return the name of the main class in the given prog, or FALSE if no
    // such class found. Uses a regular expression to find a public class with
    // a public static void main method.
    // Not totally safe as it doesn't parse the file, e.g. would be fooled
    // by a commented-out main class with a different name.
    private function getMainClass($prog)
    {
        $p = '/(^|\W)public\s+class\s+(\w+)[^{]*\{.*?(public\s+static|static\s+public)\s+void\s+main\s*\(\s*String/ms';
        if (preg_match_all($p, $prog, $matches) !== 1) {
            return false;
        } else {
            return $matches[2][0];
        }
    }

    // Get rid of the tab characters at the start of indented lines in
    // traceback output.
    public function filteredStderr()
    {
        return str_replace("\n\t", "\n        ", $this->stderr);
    }
}
