<?php
namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class Cors implements FilterInterface
{
    /**
     * Called before processing of all requests to allow Cross Origin access.
     *
     * @param array|null $arguments
     *
     * @return mixed
     */
    public function before(RequestInterface $request, $arguments = null) {
        // If it's a preflight request, return all the required headers for CORS access.
        if ($request->getMethod(true) === 'OPTIONS' && $request->hasHeader('Access-Control-Request-Method')) {
            $response = Services::response()->setStatusCode(204);
            $response = $response->setHeader('Access-Control-Allow-Origin', '*');
            $response = $response->setHeader('Access-Control-Allow-Headers', 'X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method');
            $response = $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS, PUT, HEAD, DELETE');
            return $response;
        }
    }
    /**
     * Called after processing of all requests to add all CORS filter if not present.
     *
     * @param array|null $arguments
     *
     * @return mixed
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        if (! $response->hasHeader('Access-Control-Allow-Origin')) {
            $response = $response->setHeader('Access-Control-Allow-Origin', '*');
            $response = $response->setHeader('Access-Control-Allow-Headers', 'X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method');
            $response = $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS, PUT, HEAD, DELETE');
        }
        return $response;
    }
}
