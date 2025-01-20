<?php

/*
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
 *
 */
/* ==============================================================
 *
 * This file defines the FileCache class, which manages the local file
 * cache. Files are stored in the /home/jobe/files subtree with file names
 * being the fileIDs supplied in the RestAPI 'put' requests. Normally these
 * are the MD5 hashes of the file contents. Files are stored in a 2 level
 * directory hierarchy like the Moodle file cache, with the first two letters of
 * the fileID specifying the top-level directory name and the next two letters
 * the second-level directory name. The rest of the fileID is used as the
 * linux file name.
 *
 * ==============================================================
 *
 * @copyright  2019, 2024 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace Jobe;

define('FILE_CACHE_BASE', '/home/jobe/files');
define('MD5_PATTERN', '/[0-9abcdef]{32}/');
define('MAX_PERCENT_FULL', 0.95);
define('TESTING_FILE_CACHE_CLEAR', false);

class FileCache
{
    /**
     * @param string $fileid the externally supplied file id (a.k.a. filename)
     * @return true iff the given file exists in the file cache.
     */
    public static function fileExists($fileid)
    {
        $path = self::idToPath($fileid);
        return file_exists($path);
    }


    /**
     * Get the given file. Throws FileTooLargeException if the file is
     * likely too large to load into memory. Return false if the file
     * does not exist.
     * Too large files shouldn't generally be possible, because they
     * would need to have been uploaded in the PUT request in base64,
     * which would already have hit the memory limit. However, it can
     * occur if the memory_limit in php.ini has been reduced since the
     * upload.
     * @param string $fileid the id of the required file (aka filename)
     * @return the contents of that file or false if no such file exists.
     */
    public static function fileGetContents($fileid) 
    {
        if (! self::fileExists($fileid)) {
            throw new FileCacheException("File $fileid not found");
        }
        $path = self::idToPath($fileid);
        $filesize = filesize($path);
        
        // Get current memory limit in bytes.
        // Compare with file size. Assume 20% overhead for PHP.
        $memoryLimit = self::parse_memory_limit(ini_get('memory_limit'));
        if ($filesize * 1.2 > $memoryLimit) {
            throw new FileTooLargeException($filesize, $memoryLimit);
        }
        
        return @file_get_contents($path);
    }
    
    // Helper function to convert PHP ini values (like "128M") to bytes.
    private static function parse_memory_limit($val) {
        if (function_exists('ini_parse_quantity')) {
            return ini_parse_quantity($val); // PHP >=8.2
        }
        
        $val = preg_replace('/\s+/', '', $val); // Remove spaces.
        // If empty or invalid, throw exception
        if (!preg_match('/^(-?\d+)([KMGT])?B?$/i', $val, $matches)) {
            throw new Exception("Invalid php.ini memory limit: $val");
        }
        
        $size = (int)$matches[1];
        $unit = strtoupper(isset($matches[2]) ? $matches[2] : '');
        switch ($unit) {
            case 'T': 
                $size *= 1024; // Fall through
            case 'G': 
                $size *= 1024; // Fall through
            case 'M': 
                $size *= 1024; // Fall through
            case 'K': 
                $size *= 1024;
        }
        
        return $size;
    }


    /**
     * Insert the given file contents into the file cache with the given id
     * aka filename. This is normally the md5 hash of the file contents. If
     * it doesn't appear to be, the file is stored just with the given name
     * at the top level of the cache hierarchy. Otherwise the first and
     * second pair of characters from the filename are taken as the top and
     * second level directory names respectively.
     * @param string $fileid the external file id (aka filename).
     */
    public static function filePutContents($fileid, $contents)
    {
        $freespace = disk_free_space(FILE_CACHE_BASE);
        $volumesize = disk_total_space(FILE_CACHE_BASE);
        if (TESTING_FILE_CACHE_CLEAR || $freespace / $volumesize > MAX_PERCENT_FULL) {
            self:: cleanCache();
        }
        if (preg_match(MD5_PATTERN, $fileid) !== 1) {
            $result = @file_put_contents(FILE_CACHE_BASE . '/' . $fileid, $contents);
        } else {
            $topdir = FILE_CACHE_BASE . '/' . substr($fileid, 0, 2);
            $seconddir = $topdir . '/' . substr($fileid, 2, 2);
            $fullpath = $seconddir . '/' . substr($fileid, 4);
            if (!is_dir($topdir)) {
                @mkdir($topdir, 0751);
            }
            if (!is_dir($seconddir)) {
                @mkdir($seconddir, 0751);
            }
            $result = @file_put_contents($fullpath, $contents);
        }
        return $result;
    }

    /**
     * Delete all files in the file cache that were last accessed over two
     * days ago.

     * This method is called by the filePutContents method
     * if, before adding a file, the volume containing the file cache directory
     * (/home/jobe/files) is over 95% full.
     *
     * If Jobe is providing services just to a Moodle/CodeRunner instance, this
     * method should rarely if ever be called. Usually (and indeed always, prior
     * to 2019) the only files being uploaded are the support files attached to
     * questions by question authors. The total of all such files is usually small
     * enough to leave plenty of space in a typical Linux install.

     * However, if question authors are allowing/requiring students to attach
     * files to their submissions, the file cache could fill up over time and/or
     * with large classes and/or with large attachments. Then a cache clean up
     * will be required. However, it should still be a very rare event and is
     * certainly no worse than switching CodeRunner to a new Jobe server.
     *
     * Note that CodeRunner always tries running a job without first uploading
     * files. Only if the run fails with a 404 Not Found do the files then
     * get uploaded. With this mode of operation, deleting files unused for
     * 2 days should be safe. However, non-CodeRunner users using HEAD to
     * check file existence need to be prepared to re-upload files in the event
     * of a 404 Not Found.
     */
    public static function cleanCache()
    {
        log_message('info', '*jobe*: cleaning file cache');
        @shell_exec("find " . FILE_CACHE_BASE . " -type f -atime +1 -delete &> /dev/null &");
    }


    // Return the cache file path for the given fileID.
    private static function idToPath($fileid)
    {
        if (preg_match(MD5_PATTERN, $fileid) !== 1) {
            $relativepath = $fileid;
        } else {
            $top = substr($fileid, 0, 2);
            $second = substr($fileid, 2, 2);
            $rest = substr($fileid, 4);
            $relativepath = "$top/$second/$rest";
        }
        return FILE_CACHE_BASE . '/' . $relativepath;
    }
}
