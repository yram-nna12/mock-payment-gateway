<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class GatewayApiKey implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $expected = config('PaymentGateway')->apiKey;
        if ($expected === '') {
            return service('response')->setStatusCode(503)->setJSON([
                'error' => ['code' => 'api_not_configured', 'message' => 'The payment API is not configured.'],
            ]);
        }

        $provided = $request->getHeaderLine('X-API-Key');
        if ($provided === '' || !hash_equals($expected, $provided)) {
            return service('response')->setStatusCode(401)->setJSON([
                'error' => ['code' => 'unauthorized', 'message' => 'Valid API credentials are required.'],
            ]);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
