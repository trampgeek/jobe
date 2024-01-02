<?php
/**
  * Copyright (C) 2014, 2024 Richard Lobb

 * The controller for managing posts to the 'runs' resource.
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

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use App\Models\LanguagesModel;

class Runs extends ResourceController
{
    public function __construct()
    {
        $this->langMod = new LanguagesModel();
        $this->languages = $this->langMod->findAll();
    }

    public function post()
    {
        // Note to help understand this method: the ->error and ->response methods
        // to not return. Then send the response then call exit().

        // Check this looks like a valid request.
        $data = $this->request->getJSON();
        if (!is_object($data)) {
            return $this->respond('Non-JSON post data received');
        }
        $run = $data->run_spec;
        if (!is_object($run)) {
            return $this->respond('No run_spec attribute found in post data', 400);
        }
        if (!isset($run->sourcecode)) {
            return $this->respond('run_spec is missing the required sourcecode attribute', 400);
        }

        if (!isset($run->language_id)) {
            return $this->respond('run_spec is missing the required language_id attribute', 400);
        }
        if (!isset($run->sourcefilename) || !self::is_valid_source_filename($run->sourcefilename)) {
            return $this->respond('The sourcefilename for the run_spec is missing or invalid', 400);
        }

        // If there are files, check them.
        $files = $run->file_list ?? [];
        foreach ($files as $file) {
            if (!$this->isValidFilespec($file)) {
                return $this->respond("Invalid file specifier: " . print_r($file, true), 400);
            }
        }

        // Get the the request languages and check it.
        $language = $run->language_id;
        if (!array_key_exists($language, $this->languages)) {
            return $this->respond("Language '$language' is not known", 400);
        }

        if (!isset($run->sourcefilename) || $run->sourcefilename == 'prog.java') {
            // If no sourcefilename is given or if it's 'prog.java',
            // ask the language task to provide a source filename.
            // The prog.java is a special case (i.e. hack) to support legacy
            // CodeRunner versions that left it to Jobe to come up with
            // a name (and in Java it matters).
            $run->sourcefilename = '';
        }

        // Get any input.
        $input = $run->input ?? '';

        // Get the parameters, and validate.
        $params = $run->parameters ?? [];
        $config = config('Config');
        $max_cpu_time = intval($config->cputime_upper_limit_secs);
        if (isset($params['cputime']) && intval($params['cputime']) > $max_cpu_time) {
            return $this->respond("cputime exceeds maximum allowed on this Jobe server ($max_cpu_time secs)", 400);
        }

        // Debugging is set either via a config parameter or, for a
        // specific run, by the run's debug attribute.
        // When debugging, the task run directory and its contents
        // are not deleted after the run.
        $debug = $config->debugging || ($run->debug ?? false);

        // Create the task.
        $reqdTaskClass = "\\Jobe\\" . ucwords($language) . 'Task';
        $this->task = new $reqdTaskClass($run->sourcefilename, $input, $params);

        // The nested tries here are a bit ugly, but the point is that we want to
        // to clean up the task with close() before handling the exception.
        try {
            try {
                $this->task->prepareExecutionEnvironment($run->sourcecode);

                $this->task->loadFiles($files);

                $this->log_message('debug', "runs_post: compiling job {$this->task->id}");
                $this->task->compile();

                if (empty($this->task->cmpinfo)) {
                    $this->log_message('debug', "runs_post: executing job {$this->task->id}");
                    $this->task->execute();
                }
            } finally {
                // Delete task run directory unless it's a debug run
                $this->task->close(!$debug);
            }

            // Success!
            $this->log_message('debug', "runs_post: returning 200 OK for task {$this->task->id}");
            return $this->respond($this->task->resultObject(), 200);

        // Report any errors.
        } catch (JobException $e) {
            $this->log_message('debug', 'runs_post: ' . $e->getLogMessage());
            return $this->respond($e->getMessage(), $e->getHttpStatusCode());
        } catch (OverloadException $e) {
            $this->log_message('debug', 'runs_post: overload exception occurred');
            $resultobject = new ResultObject(0, Task::RESULT_SERVER_OVERLOAD);
            return $this->respond($resultobject, 200);
        } catch (Exception $e) {
            return $this->respond('Server exception (' . $e->getMessage() . ')', 500);
        }
    }
}
