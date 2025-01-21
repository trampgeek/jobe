<?php
// Scripts to report status of the Shared Memory variable that
// controls access to Jobe tasks.
// For CLI use only. 
// You may need to change the value of $key if your Jobe installation
// is in a different directory.
try {
	$key = ftok('/var/www/html/jobe/public/index.php', 'j');
	$sem = sem_get($key);
	$gotIt = sem_acquire($sem);
	$semisfalse = $sem === false;
	echo("semisfalse = $semisfalse, key = $key, gotIt = $gotIt\n");
	$shm = shm_attach($key, 10000, 0600);
	$active = shm_get_var($shm, 1);
	print_r($active);
} catch (Exception $e) {
    echo("Exception: $e\n");
}
shm_detach($shm);
sem_release($sem);
