<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('languages/', 'Languages::get');
$routes->get('languages', 'Languages::get');
$routes->post('runs', 'Runs::post');
$routes->put('files/(:alphanum)', 'Files::put/$1');
$routes->head('files/(:alphanum)', 'Files::head/$1');
