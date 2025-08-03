# Updates made to base CodeIgniter during upgrade

## In project route

- Copy across all the following files

 - checkcorsaccess.html
 - checkshmstatus.php
 - freeallusers.php
 - install
 - license.txt
 - loadtester.php
 - minimaltest.py
 - nginx.conf
 - restapi.odt
 - restapi.pdf
 - simpletest.py
 - testsubmit.py
 
- Create a *symbolic* link to public/index.php (still called index.php)

- Copy across the entire runguard directory.

## Changes in Config directory

- Copy Jobe.php into the directory.
- Copy the Jobe routes from old into new Routes.php
- Copy updates to Autoload.php into new version
- Copy updates to App.php into new version.

## In Controllers directory

- Delete Home.php
- Copy aacross Files.php, Languages.php and Runs.php

## In Filters directory

- Copy across Cors.php and Throttle.php


## In Helpers directory

- Copy across MY_cors_helper.php

## In Libraries directory

- Copy across *all* the files (*Task.php - one for each language)

## File permissions

- Except for runguard, set ownership on all files to www-data:www-data
- Except for runguard, sudo chmod -R o-rwx

