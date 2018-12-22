<?php

declare(strict_types=1);

namespace Chiron\Tests\Http\Middleware;

use Chiron\Http\Middleware\ProxyForwardedMiddleware;
use Chiron\Http\Psr\Response;
use Chiron\Http\Psr\ServerRequest;
use Chiron\Http\Psr\Uri;
use Chiron\Tests\Utils\RequestHandlerCallable;
use PHPUnit\Framework\TestCase;

class ProxyForwardedMiddlewareTest extends TestCase
{
    public function testSchemeAndHostAndPortWithPortInHostHeader()
    {
        $request = new ServerRequest('GET', new Uri('/'), [], null, '1.1', ['REMOTE_ADDR'            => '192.168.0.1',
            'HTTP_HOST'                                                                              => 'foo.com',
            'REQUEST_SCHEME'                                                                         => 'http',
            'HTTP_X_FORWARDED_PROTO'                                                                 => 'https',
            'HTTP_X_FORWARDED_HOST'                                                                  => 'example.com:1234', ]);

        $middleware = new ProxyForwardedMiddleware();

        $handler = function ($request) use (&$scheme, &$host, &$port) {
            // simply store the values
            $scheme = $request->getUri()->getScheme();
            $host = $request->getUri()->getHost();
            $port = $request->getUri()->getPort();

            return new Response();
        };

        $middleware->process($request, new RequestHandlerCallable($handler));
        $this->assertSame('https', $scheme);
        $this->assertSame('example.com', $host);
        $this->assertSame(1234, $port);
    }

    public function testSchemeAndHostAndPortWithPortInPortHeader()
    {
        $request = new ServerRequest('GET', new Uri('/'), [], null, '1.1', ['REMOTE_ADDR'            => '192.168.0.1',
            'HTTP_HOST'                                                                              => 'foo.com',
            'REQUEST_SCHEME'                                                                         => 'http',
            'HTTP_X_FORWARDED_PROTO'                                                                 => 'https',
            'HTTP_X_FORWARDED_HOST'                                                                  => 'example.com',
            'HTTP_X_FORWARDED_PORT'                                                                  => '1234', ]);

        $middleware = new ProxyForwardedMiddleware();

        $handler = function ($request) use (&$scheme, &$host, &$port) {
            // simply store the values
            $scheme = $request->getUri()->getScheme();
            $host = $request->getUri()->getHost();
            $port = $request->getUri()->getPort();

            return new Response();
        };

        $middleware->process($request, new RequestHandlerCallable($handler));
        $this->assertSame('https', $scheme);
        $this->assertSame('example.com', $host);
        $this->assertSame(1234, $port);
    }

    public function testSchemeAndHostAndPortWithPortInHostAndPortHeader()
    {
        $request = new ServerRequest('GET', new Uri('/'), [], null, '1.1', ['REMOTE_ADDR'            => '192.168.0.1',
            'HTTP_HOST'                                                                              => 'foo.com',
            'REQUEST_SCHEME'                                                                         => 'http',
            'HTTP_X_FORWARDED_PROTO'                                                                 => 'https',
            'HTTP_X_FORWARDED_HOST'                                                                  => 'example.com:1000',
            'HTTP_X_FORWARDED_PORT'                                                                  => '2000', ]);

        $middleware = new ProxyForwardedMiddleware();

        $handler = function ($request) use (&$scheme, &$host, &$port) {
            // simply store the values
            $scheme = $request->getUri()->getScheme();
            $host = $request->getUri()->getHost();
            $port = $request->getUri()->getPort();

            return new Response();
        };

        $middleware->process($request, new RequestHandlerCallable($handler));
        $this->assertSame('https', $scheme);
        $this->assertSame('example.com', $host);
        $this->assertSame(1000, $port);
    }

    public function testNonTrustedProxies()
    {
        $request = new ServerRequest('GET', new Uri('/'), [], null, '1.1', ['REMOTE_ADDR'           => '10.0.0.1',
            'REQUEST_SCHEME'                                                                        => 'http',
            'HTTP_HOST'                                                                             => 'foo.com',
            'HTTP_X_FORWARDED_HOST'                                                                 => 'example.com:1234', ]);

        $middleware = new ProxyForwardedMiddleware(false);

        $handler = function ($request) use (&$scheme, &$host, &$port) {
            // simply store the values
            $scheme = $request->getUri()->getScheme();
            $host = $request->getUri()->getHost();
            $port = $request->getUri()->getPort();

            return new Response();
        };

        $middleware->process($request, new RequestHandlerCallable($handler));
        $this->assertSame('http', $scheme);
        $this->assertSame('foo.com', $host);
        $this->assertSame(null, $port);
    }

    /**
     * @dataProvider getLongHostNames
     */
    public function testVeryLongHosts($newHost)
    {
        $start = microtime(true);

        $request = new ServerRequest('GET', new Uri('/'), [], null, '1.1', ['HTTP_HOST'             => 'foo.com',
            'HTTP_X_FORWARDED_HOST'                                                                 => $newHost, ]);

        $middleware = new ProxyForwardedMiddleware();

        $handler = function ($request) use (&$host) {
            $host = $request->getUri()->getHost();

            return new Response();
        };

        $middleware->process($request, new RequestHandlerCallable($handler));

        $this->assertEquals('foo.com', $host);
        $this->assertLessThan(0.02, microtime(true) - $start);
    }

    /**
     * @dataProvider getHostValidities
     */
    public function testHostValidity($newHost, $isValid, $expectedHost = null, $expectedPort = null)
    {
        $request = new ServerRequest('GET', new Uri('/'), [], null, '1.1', ['HTTP_HOST' => 'foo.com',
            'HTTP_X_FORWARDED_HOST'                                                     => $newHost, ]);

        $middleware = new ProxyForwardedMiddleware();

        $handler = function ($request) use (&$host, &$port) {
            $host = $request->getUri()->getHost();
            $port = $request->getUri()->getPort();

            return new Response();
        };

        $middleware->process($request, new RequestHandlerCallable($handler));

        if ($isValid) {
            $this->assertSame($expectedHost ?: $newHost, $host);
            if ($expectedPort) {
                $this->assertSame($expectedPort, $port);
            }
        } else {
            $this->assertSame('foo.com', $host);
        }
    }

    public function getHostValidities()
    {
        return [
            ['.a', false],
            ['a..', false],
            ['a.', true],
            ["\xE9", false],
            ['localhost', true],
            ['localhost:8080', true, 'localhost', 8080],
            ['[::1]', true],
            ['[::1]:8080', true, '[::1]', 8080],
            [str_repeat('.', 101), false],
        ];
    }

    public function getLongHostNames()
    {
        return [
            ['a' . str_repeat('.abc:xyz', 1024 * 1024)],
            [str_repeat(':', 101)],
        ];
    }
}
