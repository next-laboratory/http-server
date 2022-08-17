<?php

declare(strict_types=1);

/**
 * This file is part of MaxPHP.
 *
 * @link     https://github.com/marxphp
 * @license  https://github.com/marxphp/max/blob/master/LICENSE
 */

namespace Max\Http\Server\Middleware;

use Exception;
use Max\Http\Message\Contract\RequestMethodInterface;
use Max\Http\Server\Exception\CSRFException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function Max\Utils\collect;

class VerifyCSRFToken implements MiddlewareInterface
{
    /**
     * 排除，不校验CSRF Token.
     */
    protected array $except = [
        '/',
    ];

    /**
     * 过期时间.
     */
    protected int $expires = 9 * 3600;

    /**
     * 需要被验证的请求方法.
     */
    protected array $shouldVerifyMethods = [
        RequestMethodInterface::METHOD_POST,
        RequestMethodInterface::METHOD_PUT,
        RequestMethodInterface::METHOD_PATCH,
    ];

    /**
     * @throws CSRFException|Exception
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->shouldVerify($request)) {
            if (is_null($previousToken = $request->getCookieParams()['X-XSRF-TOKEN'] ?? null)) {
                $this->abort();
            }

            $token = $this->parseToken($request);

            if ($token === '' || $token !== $previousToken) {
                $this->abort();
            }
        }

        return $this->addCookieToResponse($handler->handle($request));
    }

    /**
     * 从头部获取CSRF/XSRF Token，如果都不存在则获取表单提交的参数为__token的值
     */
    protected function parseToken(ServerRequestInterface $request): string
    {
        return $request->getHeaderLine('X-CSRF-TOKEN') ?: $request->getHeaderLine('X-XSRF-TOKEN') ?: ($request->getParsedBody()['__token'] ?? '');
    }

    /**
     * 将token添加到cookie中.
     *
     * @throws Exception
     */
    protected function addCookieToResponse(ResponseInterface $response): ResponseInterface
    {
        return $response->withCookie('X-XSRF-TOKEN', $this->newCSRFToken(), time() + $this->expires);
    }

    /**
     * 生成CSRF Token.
     *
     * @throws Exception
     */
    protected function newCSRFToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * @throws CSRFException
     */
    protected function abort()
    {
        throw new CSRFException('CSRF token is invalid', 419);
    }

    /**
     * 是否需要验证
     */
    protected function shouldVerify(ServerRequestInterface $request): bool
    {
        if (in_array($request->getMethod(), $this->shouldVerifyMethods)) {
            return ! collect($this->except)->first(function ($pattern) use ($request) {
                return $request->is($pattern);
            });
        }
        return false;
    }
}
