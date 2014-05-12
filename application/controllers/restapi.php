<?php

/* 
 * Copyright (C) 2014 Richard Lobb
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */


if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once('application/libraries/REST_Controller.php');
require_once('application/libraries/LanguageTasks.php');

define('MAX_READ', 4096);  // Max bytes to read in popen
define ('MIN_FILE_IDENTIFIER_SIZE', 8);
define ('FILE_CACHE_BASE', '/var/www/jobe/files/');



class Restapi extends REST_Controller {
    
    protected $LANGUAGES = array(
        'c'         => 'gcc 4.8.1',
        'python3'   => 'Python 3.3.2+',
        'python2'   => 'Python 2.7.5+',
        'java'      => 'javac 1.7.0_51',
        'octave'    => 'GNU Octave 3.6.4'
    );
    
    
    public function index_get() {
        $this->response('Please access this API via the runs, runresults, files or languages collections');
    }
    
    // ****************************
    //         FILES
    // ****************************

    // Put (i.e. create or update) a file
    public function files_put($fileId=FALSE) {
        if ($fileId === FALSE) {
            $this->response('No file id in URL', 400);
        }
        $contentsb64 = $this->put('file_contents');
        if ($contentsb64 === FALSE) {
            $this->response('Missing file_contents parameter');
        }

        $contents = base64_decode($contentsb64, TRUE);
        if ($contents === FALSE) {
            $this->response("Conents of file $fileId are not valid base-64", 400);
        }
        $destPath = FILE_CACHE_BASE . $fileId;
        if (file_put_contents($destPath, $contents) === FALSE) {
            $this->response("Failed to write file $destPath to cache", 500);
        }
        $this->response('', 204);
    }
    
    
    // Check file
    public function files_head($fileId) {
        if (!$fileId) {
            $this->response('Missing file ID parameter in URL', 400);
        } else if (file_exists(FILE_CACHE_BASE . $fileId)) {
            $this->response('', 200);
        } else {
            $this->response('', 404);
        }
    }
    
    // Post file
    public function files_post() {
        $this->response('Posting of files is not implemented on this server', 403);
    }
 
    // ****************************
    //        RUNS
    // ****************************
    
    public function runs_get() {
        $id = $this->get('runId');
        $this->response('No such run or run result discarded', 200);
    }
    
    
    public function runs_post() {
        if (!$run = $this->post('run_spec')) {
            $this->response('Missing or invalid run_spec parameter', 400);
        } elseif (!is_array($run) || !isset($run['sourcecode']) ||
                    !isset($run['language_id'])) {
                $this->response('Invalid run specification', 400);
        } else {
            // REST_Controller has called to_array on the JSON decoded
            // object, so we first turn it back into an object
            $run = json_decode(json_encode($run));
            
            // Now we can process the run request
            
            $this->run = $run;
            if (isset($run->file_list)) {
                $files = $run->file_list;
                foreach ($files as $file) {
                    if (!$this->is_valid_filespec($file)) {
                        $this->response(print_r($file, TRUE), 400);
                    }
                }
            } else {
                $files = array();
            }
            $sourcecode = $this->run->sourcecode;
            $language = $this->run->language_id;
            $filename = $this->run->sourcefilename;
            if (!array_key_exists($language, $this->LANGUAGES)) {
                $this->response("Language '$language' is not known", 400);
            } else {
                $reqdTaskClass = ucwords($language) . '_Task';
                $this->task = new $reqdTaskClass($sourcecode, $filename);
                $deleteFiles = !isset($this->run->debug) || !$this->run->debug;
                if (!$this->task->load_files($files, FILE_CACHE_BASE)) {
                    $this->task->close($deleteFiles);
                    $this->response('One or more of the specified files is missing/unavailable', 404);
                } else {
                    $this->task->compile();
                    if ($this->task->cmpinfo == '') {
                        $this->task->execute();
                    }
                }

                // Delete files unless it's a debug run

                $this->task->close($deleteFiles); 
            }
        }
        $this->response($this->task->resultObject(), 200);

    }
    
    // **********************
    //      RUN_RESULTS
    // **********************
    public function runresults_get()
    {
        $this->response('Unimplemented, as all submissions run immediately.', 404);
    }
    
    
    // **********************
    //      LANGUAGES
    // **********************
    public function languages_get()
    {
        $langs = array();
        foreach ($this->LANGUAGES as $id => $version) {
            $langs[] = array($id, $version);
        }
        $this->response($langs);
    }
    
    // **********************
    // Support functions
    // **********************
    private function is_valid_filespec($file) {
        return (count($file) == 2 || count($file) == 3) &&
             is_string($file[0]) &&
             is_string($file[1]) &&             
             strlen($file[0]) >= MIN_FILE_IDENTIFIER_SIZE &&
             ctype_alnum($file[0]) &&
             !empty($file[1]) &&
             ctype_alnum(str_replace('-_.', '', $file[1]));
    }    

}