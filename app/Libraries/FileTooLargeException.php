<?php

/* ==============================================================
 *
 * Jobe FileTooLargeException Exception
 *
 * ==============================================================
 *
 * @copyright  2025 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace Jobe;

class FileTooLargeException extends \Exception
{
    public function __construct($filesize, $memoryLimit, Throwable $cause = null)
    {
        $filesizek = intval($filesize / 1024);
        $memoryLimitk = intval($memoryLimit / 1024);
        parent::__construct("File size ({$filesizek}k) is unsafe with a memory limit of {$memoryLimitk}k.", 0, $cause); 
    }
}