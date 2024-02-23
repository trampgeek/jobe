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
            return addCorsHeaders($response);
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
        return addCorsHeaders($response);
    }
}
