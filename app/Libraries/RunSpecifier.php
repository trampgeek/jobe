<?php

/* ==============================================================
 *
 * This file defines the RunSpecifier class, which captures all
 * the data from the POST run-specifier.
 *
 * ==============================================================
 *
 * @copyright  2024 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace Jobe;

use App\Models\LanguagesModel;

define('MIN_FILE_IDENTIFIER_SIZE', 8);

class RunSpecifier
{
    public string $input = '';
    public string $sourcecode;
    public string $language_id;
    public string $sourcefilename='';
    public array $parameters = [];
    public array $files = [];
    public bool $debug = false;

    public function __construct($postDataJson)
    {
        if (!is_object($postDataJson)) {
            throw new JobException('Non-JSON post data received', 400);
        }
        // throw new JobException(var_dump($postDataJson, true), 500);
        $run = $postDataJson->run_spec;
        if (!is_object($run)) {
            throw new JobException('No run_spec attribute found in post data', 400);
        }

        foreach (['sourcecode', 'language_id'] as $attr) {
            if (!isset($run->$attr)) {
                throw new JobException("run_spec is missing the required attribute '$attr'", 400);
            }
            $this->$attr = $run->$attr;
        }

        $this->language_id = ucwords($this->language_id); // Normalise it.
        $languages = LanguagesModel::supportedLanguages();
        if (!array_key_exists($this->language_id, $languages)) {
            throw new JobException("Language '$language_id' is not known", 400);
        }

        // Get any input.
        $this->input = $run->input ?? '';

        // Get debug flag.
        $this->debug = $run->debug ?? false;

        // Get the parameters, and validate.
        $this->parameters = (array) ($run->parameters ?? []);
        $config = config('Jobe');
        $max_cpu_time = $config->cputime_upper_limit_secs;

        if (intval($this->parameters['cputime'] ?? 0) > $max_cpu_time) {
            throw new JobException("cputime exceeds maximum allowed on this Jobe server ($max_cpu_time secs)", 400);
        }

        if (isset($run->sourcefilename)) {
            if (!self:: isValidSourceFilename($run->sourcefilename)) {
                throw new JobException('The sourcefilename for the run_spec is illegal', 400);
            } elseif ($run->sourcefilename !== 'prog.java') {
                // As special case hack for legacy CodeRunner, ignore the almost-certainly-wrong
                // name 'prog.java' for Java programs.
                $this->sourcefilename = $run->sourcefilename;
            }
        }

        // If there are files, check them.
        $files = $run->file_list ?? [];
        foreach ($files as $file) {
            if (!$this->isValidFilespec($file)) {
                throw new JobException("Invalid file specifier: " . print_r($file, true), 400);
            }
            $this->files[] = $file;
        }
    }

    // Return true unless the given filename looks dangerous, e.g. has '/' or '..'
    // substrings. Uses code from https://stackoverflow.com/questions/2021624/string-sanitizer-for-filename
    private static function isValidSourceFilename($filename)
    {
        $sanitised = preg_replace(
            '~
    [<>:"/\\|?*]|   # file system reserved https://en.wikipedia.org/wiki/Filename#Reserved_characters_and_words
    [\x00-\x1F]|    # ctrl chars http://msdn.microsoft.com/en-us/library/windows/desktop/aa365247%28v=vs.85%29.aspx
    [\x7F\xA0\xAD]|       # non-printing characters DEL, NO-BREAK SPACE, SOFT HYPHEN
    [#\[\]@!$&\'()+,;=]|  # URI reserved https://tools.ietf.org/html/rfc3986#section-2.2
    [{}^\~`]              # URL unsafe characters https://www.ietf.org/rfc/rfc1738.txt
    ~x',
            '-',
            $filename
        );
        // Avoid ".", ".." or ".hiddenFiles"
        $sanitised = ltrim($sanitised, '.-');
        return $sanitised === $filename;
    }

    private function isValidFilespec($file)
    {
        return (count($file) == 2 || count($file) == 3) &&
         is_string($file[0]) &&
         is_string($file[1]) &&
         strlen($file[0]) >= MIN_FILE_IDENTIFIER_SIZE &&
         ctype_alnum($file[0]) &&
         strlen($file[1]) > 0 &&
         ctype_alnum(str_replace(array('-', '_', '.'), '', $file[1]));
    }
}
