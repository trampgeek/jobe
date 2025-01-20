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
use Jobe\FileTooLargeException;
use Jobe\ResultObject;
use Jobe\JobException;
use Jobe\OverloadException;
use Jobe\RunSpecifier;
use Jobe\LanguageTask;

class Runs extends ResourceController
{
    public function post()
    {
        // Extract the run object from the post data and validate.
        try {
            // Extract info from the POST data, raising JobException if bad.
            $json = $this->request->getJSON();
            $run = new RunSpecifier($json);

            // Create the task.

            $reqdTaskClass = "\\Jobe\\" . ucwords($run->language_id) . 'Task';
            $this->task = new $reqdTaskClass($run->sourcefilename, $run->input, $run->parameters);

            // The nested tries here are a bit ugly, but the point is that we want to
            // to clean up the task with close() before handling the exception.
            try {
                $this->task->prepareExecutionEnvironment($run->sourcecode, $run->files);
                log_message('debug', "runs_post: compiling job {$this->task->id}");
                $this->task->compile();
                if (empty($this->task->cmpinfo)) {
                    log_message('debug', "runs_post: executing job {$this->task->id}");
                    $this->task->execute();
                }
            } finally {
                // Free user and delete task run directory unless it's a debug run.
                $this->task->close(!$run->debug);
            }

            // Success!
            log_message('debug', "runs_post: returning 200 OK for task {$this->task->id}");
            return $this->respond($this->task->resultObject(), 200);

        // Report any errors.
        } catch (JobException $e) {
            $message = $e->getMessage();
            log_message('error', "runs_post: $message");
            return $this->respond($message, $e->getHttpStatusCode());
        } catch (OverloadException $e) {
            log_message('error', 'runs_post: overload exception occurred');
            $resultobject = new ResultObject(0, LanguageTask::RESULT_SERVER_OVERLOAD);
            return $this->respond($resultobject, 200);
        } catch (FileTooLargeException $e) {
            $message = $e->getMessage();
            log_message('error', "runs_post: $message");
            return $this->respond($message, 500);
        } catch (\Throwable $e) {
            $message = 'Server exception (' . $e->getMessage() . ')';
            log_message('error', "runs_post: $message");
            return $this->respond($message, 500);
        }
    }
}
