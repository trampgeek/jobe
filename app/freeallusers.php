<?php
// Program to reset the Memory variable that
// controls access to Jobe tasks, essentially
// freeing all users. For CLI use only and
// then only in emergencies!
$NUM_USERS = 8; // Adjust as required.
$ACTIVE_USERS = 1;  // ID for active-users variable.
try {
	$key = ftok('/var/www/html/jobe/public/index.php', 'j');
	$sem = sem_get($key);
	$gotIt = sem_acquire($sem);
	$semisfalse = $sem === false;
	echo("semisfalse = $semisfalse, key = $key, gotIt = $gotIt\n");
	$shm = shm_attach($key, 10000, 0600);
	$active = shm_get_var($shm, $ACTIVE_USERS);
	print_r($active);
	for ($i=0; $i < $NUM_USERS; $i++) {
	   $active[$i] = 0;
	}
	shm_put_var($shm, $ACTIVE_USERS, $active);
} catch (Exception $e) {
    echo("Exception: $e\n");
}
shm_detach($shm);
sem_release($sem);

