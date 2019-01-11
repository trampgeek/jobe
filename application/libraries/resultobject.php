<?php defined('BASEPATH') OR exit('No direct script access allowed');

/* ==============================================================
 *
 * This file defines the ResultObject class, which is the type of
 * response from a job submission (but returned as a JSON object).
 *
 * ==============================================================
 *
 * @copyright  2014 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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
        $this->cmpinfo = $this->clean($cmpinfo);
        $this->stdout = $this->clean($stdout);
        $this->stderr = $this->clean($stderr);
    }


    protected static function clean(&$s) {
        // If the given parameter string is valid utf-8, it is returned
        // as is. Otherise, the return value is a copy of $s sanitised by
        // replacing all control chars except newlines, tabs and returns with hex
        // equivalents. Implemented here because non-utf8 output causes the
        // json-encoding of the result to fail.
        if (mb_check_encoding($s, 'UTF-8')) {
            return $s;
        } else {
            $new_s = '';  // Output string
            $n = strlen($s);
            for ($i = 0; $i < $n; $i++) {
                $c = $s[$i];
                if (($c != "\n" && $c != "\r" && $c != "\t" && $c < " ") || $c > "\x7E") {
                    $c = '\\x' . sprintf("%02x", ord($c));
                }
                $new_s .= $c;
            }

            return $new_s;
        }
    }
}