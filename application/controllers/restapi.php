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
require_once('application/libraries/LanguageTask.php');
require_once('application/libraries/JobException.php');
require_once('application/libraries/resultobject.php');

define('MAX_READ', 4096);  // Max bytes to read in popen
define ('MIN_FILE_IDENTIFIER_SIZE', 8);
define('LANGUAGE_CACHE_FILE', '/tmp/jobe_language_cache_file');



class Restapi extends REST_Controller {

    protected $file_cache_base = NULL;
    protected $languages = array();

    // Constructor loads the available languages from the libraries directory.
    // [But to handle CORS (Cross Origin Resource Sharing) it first issues
    // the access-control headers, and then quits if it's an OPTIONS request,
    // which is the "pre-flight" browser generated request to check access.]
    // See http://stackoverflow.com/questions/15602099/http-options-error-in-phil-sturgeons-codeigniter-restserver-and-backbone-js
    public function __construct()
    {
        header('Access-Control-Allow-Origin: *');
        header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, HEAD, DELETE");
        $method = $_SERVER['REQUEST_METHOD'];
        if($method == "OPTIONS") {
            die();
        }
        parent::__construct();
        $this->file_cache_base = FCPATH . '/files/';

        $this->languages = $this->supported_languages();

        if ($this->config->item('rest_enable_limits')) {
            $this->load->config('per_method_limits');
            $limits = $this->config->item('per_method_limits');
            foreach ($limits as $method=>$limit) {
                $this->methods[$method]['limit'] = $limit;
            }
        }
    }


    protected function log($type, $message) {
        // Call log_message with the same parameters, but prefix the message
        // by *jobe* for easy identification.
        log_message($type, '*jobe* ' . $message);
    }


    protected function error($message, $httpCode=400) {
        // Generate the http response containing the given message with the given
        // HTTP response code. Log the error first.
        $this->log('error', $message);
        $this->response($message, $httpCode);
    }


    public function index_get() {
        $this->response('Please access this API via the runs, runresults, files or languages collections', 404);
    }

    // ****************************
    //         FILES
    // ****************************

    // Put (i.e. create or update) a file
    public function files_put($fileId=FALSE) {
        if ($fileId === FALSE) {
            $this->error('No file id in URL');
        }
        $contentsb64 = $this->put('file_contents', FALSE);
        if ($contentsb64 === FALSE) {
            $this->error('put: missing file_contents parameter');
        }

        $contents = base64_decode($contentsb64, TRUE);
        if ($contents === FALSE) {
            $this->error("put: contents of file $fileId are not valid base-64");
        }
        $destPath = $this->file_cache_base . $fileId;

        if (file_put_contents($destPath, $contents) === FALSE) {
            $this->error("put: failed to write file $destPath to cache", 500);
        }
        $len = strlen($contents);
        $this->log('debug', "Put file $fileId, size $len");
        $this->response(NULL, 204);
    }


    // Check file
    public function files_head($fileId) {
        if (!$fileId) {
            $this->error('head: missing file ID parameter in URL');
        } else if (file_exists($this->file_cache_base .$fileId)) {
            $this->log('debug', "head: file $fileId exists");
            $this->response(NULL, 204);
        } else {
            $this->log('debug', "head: file $fileId not found");
            $this->response(NULL, 404);
        }
    }

    // Post file
    public function files_post() {
        $this->error('file_post: not implemented on this server', 403);
    }

    // ****************************
    //        RUNS
    // ****************************

    public function runs_get() {
        $id = $this->get('runId');
        $this->error('runs_get: no such run or run result discarded', 200);
    }


    public function runs_post() {
        global $CI;

        // Note to help understand this method: the ->error and ->response methods
        // to not return. Then send the response then call exit().

        // Check this looks like a valid request.
        if (!$run = $this->post('run_spec', false)) {
            $this->error('runs_post: missing or invalid run_spec parameter', 400);
        }
        if (!is_array($run) || !isset($run['sourcecode']) ||
                !isset($run['language_id'])
        ) {
            $this->error('runs_post: invalid run specification', 400);
        }

        // REST_Controller has called to_array on the JSON decoded
        // object, so we must first turn it back into an object.
        $run = (object) $run;

        // If there are files, check them.
        if (isset($run->file_list)) {
            $files = $run->file_list;
            foreach ($files as $file) {
                if (!$this->is_valid_filespec($file)) {
                    $this->error("runs_post: invalid file specifier: " . print_r($file, true), 400);
                }
            }
        } else {
            $files = array();
        }

        // Get the the request languages and check it.
        $language = $run->language_id;
        if (!array_key_exists($language, $this->languages)) {
            $this->response("Language '$language' is not known", 400);
        }
        $reqdTaskClass = ucwords($language) . '_Task';
        if (!isset($run->sourcefilename) || $run->sourcefilename == 'prog.java') {
            // If no sourcefilename is given or if it's 'prog.java',
            // ask the language task to provide a source filename.
            // The prog.java is a special case (i.e. hack) to support legacy
            // CodeRunner versions that left it to Jobe to come up with
            // a name (and in Java it matters).
            $run->sourcefilename = '';
        }
        require_once($this->get_path_for_language_task($language));

        // Get any input.
        $input = isset($run->input) ? $run->input : '';

        // Get the parameters, and validate.
        $params = isset($run->parameters) ? $run->parameters : array();
        if (isset($params['cputime']) &&
                intval($params['cputime']) > intval($CI->config->item('cputime_upper_limit_secs'))
        ) {
            $this->response("cputime exceeds maximum allowed on this Jobe server", 400);
        }

        // Debugging is set either via a config parameter or, for a
        // specific run, by the run's debug attribute.
        // When debugging, files are not deleted after the run.
        $debug = $this->config->item('debugging') ||
                (isset($run->debug) && $run->debug);

        // Create the task.
        $this->task = new $reqdTaskClass($run->sourcefilename, $input, $params);

        // The nested tries here are a bit ugly, but the point is that we want to
        // to clean up the task with close() before handling the exception.
        try {
            try {
                $this->task->prepare_execution_environment($run->sourcecode);

                $this->task->load_files($files, $this->file_cache_base);

                $this->log('debug', "runs_post: compiling job {$this->task->id}");
                $this->task->compile();

                if (empty($this->task->cmpinfo)) {
                    $this->log('debug', "runs_post: executing job {$this->task->id}");
                    $this->task->execute();
                }

            } finally {
                // Delete files unless it's a debug run
                $this->task->close(!$debug);
            }

            // Success!
            $this->log('debug', "runs_post: returning 200 OK for task {$this->task->id}");
            $this->response($this->task->resultObject(), 200);

        // Report any errors.
        } catch (JobException $e) {
            $this->log('debug', 'runs_post: ' . $e->getLogMessage());
            $this->response($e->getMessage(), $e->getHttpStatusCode());

        } catch (OverloadException $e) {
            $this->log('debug', 'runs_post: overload exception occurred');
            $resultobject = new ResultObject(0, Task::RESULT_SERVER_OVERLOAD);
            $this->response($resultobject, 200);

        } catch (Exception $e) {
            $this->response('Server exception (' . $e->getMessage() . ')', 500);
        }
    }

    // **********************
    //      RUN_RESULTS
    // **********************
    public function runresults_get()
    {
        $this->error('runresults_get: unimplemented, as all submissions run immediately.', 404);
    }


    // **********************
    //      LANGUAGES
    // **********************
    public function languages_get()
    {
        $this->log('debug', 'languages_get called');
        $languages = $this->supported_languages();
        $langs = array();
        foreach($languages as $lang => $version) {
            $langs[] = array($lang, $version);
        }
        $this->response($langs, 200);
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
             strlen($file[1]) > 0 &&
             ctype_alnum(str_replace(array('-', '_', '.'), '', $file[1]));
    }


    // Return an associative array mapping language name to language version
    // string for all supported languages (and only supported languages).
    private function supported_languages() {
        if (file_exists(LANGUAGE_CACHE_FILE)) {
            $langsJson = @file_get_contents(LANGUAGE_CACHE_FILE);
            $langs = json_decode($langsJson);

            // Security check, since this file is stored in /tmp where anyone could write it.
            foreach ($langs as $lang => $version) {
                if (!preg_match('/[a-z0-9]+/', $lang)) {
                    $langs = null; // Looks like the file has been tampered with, re-compute.
                    break;
                }
                if (!is_readable($this->get_path_for_language_task($lang))) {
                    $langs = null; // Looks like the file has been tampered with, re-compute.
                    break;
                }
            }
        }
        if (empty($langs) || (is_array($langs) && isset($langs[0]))) {
            $this->log('debug', 'Missing or corrupt languages cache file ... rebuilding it.');
            $langs = array();
            $library_files = scandir('application/libraries');
            foreach ($library_files as $file) {
                $end = '_task.php';
                $pos = strpos($file, $end);
                if ($pos == strlen($file) - strlen($end)) {
                    $lang = substr($file, 0, $pos);
                    require_once($this->get_path_for_language_task($lang));
                    $class = $lang . '_Task';
                    $version = $class::getVersion();
                    if ($version) {
                        $langs[$lang] = $version;
                    }
                }
            }

            $langsJson = json_encode($langs);
            file_put_contents(LANGUAGE_CACHE_FILE, $langsJson);
        }
        return $langs;
    }

    /**
     * Get the path to the file that defines the language task for a given language.
     *
     * @param $lang the language of interest, e.g. cpp.
     * @return string the corresponding code path.
     */
    private function get_path_for_language_task($lang) {
        return 'application/libraries/' . $lang . '_task.php';
    }
}
