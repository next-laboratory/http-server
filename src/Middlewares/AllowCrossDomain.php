<?php

declare(strict_types=1);

/**
 * This file is part of MaxPHP.
 *
 * @link     https://github.com/marxphp
 * @license  https://github.com/marxphp/max/blob/master/LICENSE
 */

namespace Max\Http\Server\Middlewares;

use Max\Http\Message\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AllowCrossDomain implements MiddlewareInterface
{
    /** @var array 允许域，全部可以使用`*` */
    protected array $allowOrigin = ['*'];

    /** @var array 附加的响应头 */
    protected array $addedHeaders = [
        'Access-Control-Allow-Credentials' => 'true',
        'Access-Control-Max-Age'           => 1800,
        'Access-Control-Allow-Methods'     => 'GET, POST, PATCH, PUT, DELETE, OPTIONS',
        'Access-Control-Allow-Headers'     => 'Authorization, Content-Type, If-Match, If-Modified-Since, If-None-Match, If-Unmodified-Since, X-CSRF-TOKEN, X-Requested-With',
    ];

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $allowOrigin = in_array('*', $this->allowOrigin) ? '*' : $request->getHeaderLine('Origin');
        if ($allowOrigin !== '') {
            $headers                                = $this->addedHeaders;
            $headers['Access-Control-Allow-Origin'] = $allowOrigin;
            if (strcasecmp($request->getMethod(), 'OPTIONS') === 0) {
                return new Response(204, $headers);
            }
            $response = $handler->handle($request);
            foreach ($headers as $name => $header) {
                $response = $response->withHeader($name, $header);
            }
            return $response;
        }

        return $handler->handle($request);
    }
}
