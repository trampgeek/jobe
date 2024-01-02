<?php

/**
  * Copyright (C) 2014, 2024 Richard Lobb

 * A pseudo model to manage the set of languages implemented
 * by Jobe. It's a pseudomodel that doesn't extend the base
 * Model class as it's not asociated with a database table.
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
namespace App\Models;

define('LANGUAGE_CACHE_FILE', '/tmp/jobe_language_cache_file');

class LanguagesModel
{
    public function findAll()
    {
        log_message('debug', 'INDEX languages called');
        $languages = $this->supportedLanguages();
        $langs = array();
        foreach ($languages as $lang => $version) {
            $langs[] = array($lang, $version);
        }
        return $langs;
    }

    // Return an associative array mapping language name to language version
    // string for all supported languages (and only supported languages).
    private function supportedLanguages()
    {
        if (file_exists(LANGUAGE_CACHE_FILE)) {
            $langsJson = @file_get_contents(LANGUAGE_CACHE_FILE);
            $langs = json_decode($langsJson, true);

            // Security check, since this file is stored in /tmp where anyone could write it.
            foreach ($langs as $lang => $version) {
                if (!preg_match('/[a-zA-Z0-9]+/', $lang)) {
                    $langs = null; // Looks like the file has been tampered with, re-compute.
                    break;
                }
                if (!is_readable($this->getPathForLanguageTask($lang))) {
                    $langs = null; // Looks like the file has been tampered with, re-compute.
                    break;
                }
            }
        }
        if (empty($langs) || (is_array($langs) && isset($langs[0]))) {
            log_message('debug', 'Missing or corrupt languages cache file ... rebuilding it.');
            $langs = [];
            $library_files = scandir(APPPATH . '/Libraries');
            foreach ($library_files as $file) {
                $end = 'Task.php';
                $pos = strpos($file, $end);
                if ($pos === strlen($file) - strlen($end)) {
                    $lang = substr($file, 0, $pos);
                    $class = "\\Jobe\\" . $lang . 'Task';
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
    public function getPathForLanguageTask($lang)
    {
        return APPPATH . '/Libraries/' . $lang . 'Task.php';
    }
}
