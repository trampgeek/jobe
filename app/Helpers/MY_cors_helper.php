<?php
/**
 * Add the CORS headers to the given response and return it, unless it already has a
 * an Access-Control-Allow-Origin header, in which case return it unchanged.
 * @param $response The response object to be updated.
 * @return The updated response object.
 */

 use CodeIgniter\HTTP\Response;
 
function addCorsHeaders(Response $response)
{
    if (! $response->hasHeader('Access-Control-Allow-Origin')) {
            $response = $response->setHeader('Access-Control-Allow-Origin', '*');
            $response = $response->setHeader('Access-Control-Allow-Headers', 'X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method');
            $response = $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS, PUT, HEAD, DELETE');
    }
    return $response;
}