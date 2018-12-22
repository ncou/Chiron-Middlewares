<?php

declare(strict_types=1);

namespace Chiron\Tests\Http\Middleware;

use Chiron\Http\Middleware\RedirectTrailingSlashMiddleware;
use Chiron\Http\Psr\Response;
use Chiron\Http\Psr\ServerRequest;
use Chiron\Http\Psr\Uri;
use Chiron\Tests\Utils\RequestHandlerCallable;
use PHPUnit\Framework\TestCase;

class RedirectTrailingSlashMiddlewareTest extends TestCase
{
    public function removeProvider(): array
    {
        return [
            ['/foo/bar', '/foo/bar'],
            ['/foo/bar/', '/foo/bar'],
            ['/', '/'],
            ['', '/'],
        ];
    }

    /**
     * @dataProvider removeProvider
     */
    public function testRemove(string $uri, string $result)
    {
        $request = new ServerRequest('GET', new Uri($uri));

        $middleware = new RedirectTrailingSlashMiddleware();
        $handler = function ($request) use (&$path) {
            $path = $request->getUri()->getPath();

            return new Response();
        };

        $middleware->process($request, new RequestHandlerCallable($handler));

        $this->assertEquals($result, $path);
    }

    public function addProvider(): array
    {
        return [
            ['/foo/bar', '/foo/bar/'],
            ['/foo/bar/', '/foo/bar/'],
            ['/', '/'],
            ['', '/'],
            ['/index.html', '/index.html'],
            ['/index', '/index/'],
        ];
    }

    /**
     * @dataProvider addProvider
     */
    public function testAdd(string $uri, string $result)
    {
        $request = new ServerRequest('GET', new Uri($uri));

        $middleware = new RedirectTrailingSlashMiddleware(true);
        $handler = function ($request) use (&$path) {
            $path = $request->getUri()->getPath();

            return new Response();
        };

        $middleware->process($request, new RequestHandlerCallable($handler));

        $this->assertEquals($result, $path);
    }

    public function testRedirect()
    {
        $request = new ServerRequest('GET', new Uri('/foo/bar/'));

        $middleware = (new RedirectTrailingSlashMiddleware())->redirect(true);
        $handler = function ($request) {
            return new Response();
        };

        $response = $middleware->process($request, new RequestHandlerCallable($handler));

        $this->assertEquals(301, (string) $response->getStatusCode());
        $this->assertEquals('/foo/bar', $response->getHeaderLine('Location'));
    }
}
