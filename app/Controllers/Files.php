<?php
/**
 * Copyright (C) 2014, 2024 Richard Lobb

* The controller for managing PUTs and HEADs to the 'files' resource.
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
use Jobe\ResultObject;
use Jobe\JobException;
use Jobe\FileCache;

class Files extends ResourceController
{

    // Put (i.e. create or update) a file
    public function put($fileId = false)
    {
        log_message('debug', "Put file called with fileId $fileId");
        if ($fileId === false) {
            return $this->respond('No file id in URL', 400);
        }
        $json = $this->request->getJSON();
        if (!$json || !isset($json->file_contents)) {
            return $this->respond('put: missing file_contents parameter', 400);
        }

        $contents = base64_decode($json->file_contents, true);
        if ($contents === false) {
            return $this->respond("put: contents of file $fileId are not valid base-64", 400);
        }

        if (FileCache::filePutContents($fileId, $contents) === false) {
            return $this->respond("put: failed to write file $fileId to cache", 500);
        }
        $len = strlen($contents);
        log_message('debug', "Put file $fileId, size $len");
        return $this->respond(null, 204);
    }


    // Check file
    public function head($fileId)
    {
        if (!$fileId) {
            return $this->respond('head: missing file ID parameter in URL', 400);
        } elseif (FileCache::fileExists($fileId)) {
            log_message('debug', "head: file $fileId exists");
            return $this->respond(null, 204);
        } else {
            log_message('debug', "head: file $fileId not found");
            return $this->respond(null, 404);
        }
    }
}
