<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 *
$routes->get('jobe/languages/', 'Languages::get');
$routes->get('jobe/languages', 'Languages::get');
$routes->post('jobe/runs', 'Runs::post');
$routes->put('jobe/files/(:alphanum)', 'Files::put/$1');
$routes->head('jobe/files/(:alphanum)', 'Files::head/$1');
*/

/*
  Support legacy URIs
*/
$routes->get('/restapi/languages/', 'Languages::get');
$routes->get('/restapi/languages', 'Languages::get');
$routes->post('/restapi/runs', 'Runs::post');
$routes->put('/restapi/files/(:alphanum)', 'Files::put/$1');
$routes->head('/restapi/files/(:alphanum)', 'Files::head/$1');
$routes->options('(:any)', '', ['filter' => 'cors']);
