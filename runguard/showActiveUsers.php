<?php

// A debugging script to execute from the command line to report the current
// status of the active users variable in the shared memory area.
// You'll have to tweak the $key = ... line to correctly reflect your actual
// web root.

define('ACTIVE_USERS', 1);  // The key for the shared memory active users array
$key = ftok('/var/www/html/jobe/application/libraries/LanguageTask.php', 'j');
$sem = sem_get($key);
sem_acquire($sem);
$shm = shm_attach($key);
$active = shm_get_var($shm, ACTIVE_USERS);
print_r($active);
$i = 0;
for ($i = 0; $i < 10; $i++) {
    echo "User $i: {$active[$i]}\n";
}
shm_detach($shm);
sem_release($sem);




