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

class JobException extends Exception {
    protected $logmessage;
    protected $httpstatuscode;

    public function __construct($message, $logmessage, $httpstatuscode, Throwable $cause = null) {
        parent::__construct($message, 0, $cause);
        $this->logmessage = $logmessage;
        $this->httpstatuscode = $httpstatuscode;
    }

    public function getLogMessage() {
        return $this->logmessage;
    }

    public function getHttpStatusCode() {
        return $this->httpstatuscode;
    }
}
