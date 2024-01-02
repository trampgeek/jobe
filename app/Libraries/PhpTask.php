<?php

/* ==============================================================
 *
 * PHP5
 *
 * ==============================================================
 *
 * @copyright  2014 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace Jobe;

class PhpTask extends Task
{
    public function __construct($filename, $input, $params)
    {
        parent::__construct($filename, $input, $params);
        $this->default_params['interpreterargs'] = ['--no-php-ini'];
        $this->default_params['memorylimit'] = 400; // MB (Greedy PHP)
    }

    public static function getVersionCommand()
    {
        return array('php --version', '/PHP ([0-9._]*)/');
    }

    public function compile()
    {
        $outputLines = array();
        $returnVar = 0;
        list($output, $compileErrs) = $this->runInSandbox("/usr/bin/php -l {$this->sourceFileName}");
        if (empty($compileErrs)) {
            $this->cmpinfo = '';
            $this->executableFileName = $this->sourceFileName;
        } else {
            if ($output) {
                $this->cmpinfo = $output . '\n' . $compileErrs;
            } else {
                $this->cmpinfo = $compileErrs;
            }
        }
    }


    // A default name for PHP programs
    public function defaultFileName($sourcecode)
    {
        return 'prog.php';
    }


    public function getExecutablePath()
    {
        return '/usr/bin/php';
    }


    public function getTargetFile()
    {
        return $this->sourceFileName;
    }
}
