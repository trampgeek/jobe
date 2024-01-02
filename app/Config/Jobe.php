<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Jobe extends BaseConfig
{
    /*
    |--------------------------------------------------------------------------
    | Jobe parameters
    |--------------------------------------------------------------------------
    |
    | This section of the config file is not CodeIgniter standard. It contains
    | constants related to the Jobe server itself.
    |
    | jobe_max_users controls how many jobs can be run by the server at any
    | one time. It *must* agree with the number of users with names jobe01,
    | jobe02, jobe03 etc (which is how the install script will set things up).
    |
    | Clean up path is a semicolon-separated list of directories that are
    | writable by all, to be cleaned on completion of a job.
    |
    */
    public int $jobe_max_users = 8;
    public int $jobe_wait_timeout = 10;  // Max number of secs to wait for a free Jobe user.
    public int $cputime_upper_limit_secs = 120;
    public string $clean_up_path = '/tmp;/var/tmp;/var/crash;/run/lock;/var/lock';
    public bool $debugging = false;
    public string $python3_version = 'python3'; # /usr/bin/<python3_version> is the python to run
    // NB: if you modify the python3_version configuration you will also need to
    // reboot the server or delete the file /tmp/jobe_language_cache_file (which
    // might be hidden away in a systemd-private directory, depending on your Linux
    // version).

    /*
    |--------------------------------------------------------------------------
    | Jobe parameters: CPU pinning for jobs  [Thanks Marcus Klang
    |--------------------------------------------------------------------------
    |
    | This section of the config file controls processor affinity, i.e. pinning
    | runguard tasks to a particular CPU core.
    |
    | The way task are pinned is to use the jobe user id modulo the number of
    | cores. Under a load which requires more compute than is available
    | this yields linear slowdown for each job. Assigning jobs to a specific
    | core yields more predictable behaviour during extreme overallocation.
    | This is more significant with machines that have multi-socket CPUs
    | which can have larger memory/cache penalites when tasks are transferred
    | between cores.
    |
    | Consider setting jobe_max_users to be a multiple of num_cores, otherwise
    | there will be imbalance under 100% job allocation.
    |
    | Enabling this option restricts each job to a singular core, regardless
    | of number of spawned threads. Multiple threads will work fine but they
    | cannot run perfectly concurrent, a context switch must occur.
    */
    public bool $cpu_pinning_enabled = false;
    public int $cpu_pinning_num_cores = 8; // Update to number of server cores

    /*
    |--------------------------------------------------------------------------
    | Jobe parameters: Extra Java/Javac arguments [Thanks Marcus Klang
    |--------------------------------------------------------------------------
    |
    | This section of the config file adds extra flags to java and javac
    |
    | Provided examples tells java/javac that there is only 1 core, which
    | reduces the number of spawned threads when compiling. This option can be
    | used to provide a better experience when many users are using jobe.
    */
    public string $javac_extraflags = ''; //'-J-XX:ActiveProcessorCount=1';
    public string $java_extraflags = ''; //'-XX:ActiveProcessorCount=1';
}
