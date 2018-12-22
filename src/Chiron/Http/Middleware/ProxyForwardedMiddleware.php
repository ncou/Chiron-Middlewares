<?php

declare(strict_types=1);

namespace Chiron\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ProxyForwardedMiddleware implements MiddlewareInterface
{
    /**
     * Boolean to decide if we use the proxy informations or not.
     *
     * @var bool
     */
    private $trustProxy;

    /**
     * Constructor.
     *
     * @param array $trustedProxies List of IP addresses of trusted proxies
     */
    public function __construct(bool $trustProxy = true)
    {
        $this->trustProxy = $trustProxy;
    }

    /**
     * Override the request URI's scheme, host and port as determined from the proxy headers.
     *
     * @param ServerRequestInterface  $request request
     * @param RequestHandlerInterface $handler
     *
     * @return object ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->trustProxy) {
            $uri = $request->getUri();

            $uri = $this->applyProtoHeader($request, $uri);
            $uri = $this->applyPortHeader($request, $uri);
            $uri = $this->applyHostHeader($request, $uri);

            $request = $request->withUri($uri);
        }

        return $handler->handle($request);
    }

    private function applyProtoHeader(ServerRequestInterface $request, UriInterface $uri)
    {
        if ($request->hasHeader('X-Forwarded-Proto')) {
            $proto = trim(current(explode(',', $request->getHeaderLine('X-Forwarded-Proto'))));
            $proto = in_array(strtolower($proto), ['https', 'on', 'ssl', '1']) ? 'https' : 'http';

            $uri = $uri->withScheme($proto);
        }

        return $uri;
    }

    private function applyPortHeader(ServerRequestInterface $request, UriInterface $uri)
    {
        if ($request->hasHeader('X-Forwarded-Port')) {
            $port = trim(current(explode(',', $request->getHeaderLine('X-Forwarded-Port'))));
            if ($this->isValidPortNumber($port)) {
                $uri = $uri->withPort((int) $port);
            }
        }

        return $uri;
    }

    private function applyHostHeader(ServerRequestInterface $request, UriInterface $uri)
    {
        if ($request->hasHeader('X-Forwarded-Host')) {
            $host = trim(current(explode(',', $request->getHeaderLine('X-Forwarded-Host'))));
            $port = null;

            if ($this->isValidHostName($host)) {
                // IPV6 address are enclosed in square brackets : https://tools.ietf.org/html/rfc7239#page-9
                if (preg_match('/^(\[[a-fA-F0-9:.]+\])(:\d+)?\z/', $host, $matches)) {
                    $host = $matches[1];
                    if (isset($matches[2])) {
                        $port = (int) substr($matches[2], 1);
                    }
                } else { // String Host or IPV4 Host
                    $hostHeaderParts = explode(':', $host);
                    if (isset($hostHeaderParts[1])) {
                        $port = $hostHeaderParts[1];
                    }
                    $host = $hostHeaderParts[0];
                }
                $uri = $uri->withHost($host);
                if ($port) {
                    $uri = $uri->withPort($port);
                }
            }
        }

        return $uri;
    }

    /**
     * Check that a given host is a valid Host Name.
     *
     * As the host can come from the user (HTTP_HOST and depending on the configuration, SERVER_NAME too can come from the user)
     * check that it does not contain forbidden characters (see RFC 952 and RFC 2181)
     *
     * @see https://symfony.com/blog/cve-2014-5244-denial-of-service-with-a-malicious-http-host-header
     *
     * @param string $host
     *
     * @return bool
     */
    private function isValidHostName(string $host): bool
    {
        // Limit the length of the host name to 1000 bytes to prevent DoS attacks with long host names (it could slow down preg_match function).
        return strlen($host) <= 1000 && substr_count($host, '.') <= 100 && substr_count($host, ':') <= 100 && preg_match('/^\[?(?:[a-zA-Z0-9-:\]_]+\.?)+$/', $host);
    }

    /**
     * Check that a given port is a valid Port Number.
     *
     * Must be numerical and beetween 1 an 65535
     *
     *
     * @param string $host
     *
     * @return bool
     */
    private function isValidPortNumber(string $port): bool
    {
        return strlen($port) <= 5 && preg_match('/^\d+\z/', $port) && ! (1 > (int) $port || 0xffff < (int) $port);
    }
}
