<?php

declare(strict_types=1);

/**
 * This file is part of MaxPHP.
 *
 * @link     https://github.com/marxphp
 * @license  https://github.com/marxphp/max/blob/master/LICENSE
 */

namespace Max\Http\Server\ResponseEmitter;

use Max\Http\Message\Cookie;
use Max\Http\Message\Stream\FileStream;
use Max\Http\Server\Contract\ResponseEmitterInterface;
use Psr\Http\Message\ResponseInterface;
use Swoole\Http\Response;

class SwooleResponseEmitter implements ResponseEmitterInterface
{
    /**
     * @param Response $sender
     */
    public function emit(ResponseInterface $psrResponse, $sender = null)
    {
        $sender->status($psrResponse->getStatusCode(), $psrResponse->getReasonPhrase());
        foreach ($psrResponse->getHeader('Set-Cookie') as $cookie) {
            $this->sendCookie(Cookie::parse($cookie), $sender);
        }
        $psrResponse = $psrResponse->withoutHeader('Set-Cookie');
        foreach ($psrResponse->getHeaders() as $key => $value) {
            $sender->header($key, implode(', ', $value));
        }
        $body = $psrResponse->getBody();
        switch (true) {
            case $body instanceof FileStream:
                $sender->sendfile($body->getMetadata('uri'), $body->tell(), max($body->getLength(), 0));
                break;
            default:
                $sender->end($body->getContents());
        }
        $body?->close();
    }

    protected function sendCookie(Cookie $cookie, Response $response): void
    {
        $response->cookie(
            $cookie->getName(),
            $cookie->getValue(),
            $cookie->getExpires(),
            $cookie->getPath(),
            $cookie->getDomain(),
            $cookie->isSecure(),
            $cookie->isHttponly(),
            $cookie->getSameSite()
        );
    }
}
