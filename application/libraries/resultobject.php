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
        $this->cmpinfo = $cmpinfo;
        $this->stdout = $stdout;
        $this->stderr = $stderr;
    }
}