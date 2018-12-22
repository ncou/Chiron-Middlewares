<?php

declare(strict_types=1);

namespace Chiron\Tests\Http\Middleware;

use Chiron\Http\Middleware\RedirectHttpsMiddleware;
use Chiron\Http\Psr\Response;
use Chiron\Http\Psr\ServerRequest;
use Chiron\Http\Psr\Uri;
use Chiron\Tests\Utils\RequestHandlerCallable;
use PHPUnit\Framework\TestCase;

class RedirectHttpsMiddlewareTest extends TestCase
{
    protected $middleware;

    public $request;

    protected function setUp()
    {
        parent::setUp();
        $this->middleware = new RedirectHttpsMiddleware();
        $this->request = new ServerRequest('GET', new Uri('/'));
    }

    public function testIsHttps()
    {
        $request = $this->request->withUri(
            new Uri('https://domain.com')
        );
        $handler = function ($request) {
            return (new Response())->write('SUCCESS');
        };
        $middleware = $this->middleware;
        $result = $middleware->process($request, new RequestHandlerCallable($handler));
        $this->assertEquals('SUCCESS', (string) $result->getBody());
    }

    public function testIsHttpsCaseSensitive()
    {
        $request = $this->request->withUri(
            new Uri('hTTpS://domain.com')
        );
        $handler = function ($request) {
            return (new Response())->write('SUCCESS');
        };
        $middleware = $this->middleware;
        $result = $middleware->process($request, new RequestHandlerCallable($handler));
        $this->assertEquals('SUCCESS', (string) $result->getBody());
    }

    public function testNotHttps()
    {
        $request = $this->request->withUri(
            new Uri('http://domain.com')
        );
        $handler = function ($request) {
            throw new \Exception('Should not make it here');
        };
        $middleware = $this->middleware;
        $result = $middleware->process($request, new RequestHandlerCallable($handler));
        $this->assertEquals(301, $result->getStatusCode());
        $this->assertEquals('https://domain.com', $result->getHeaderLine('Location'));
    }

    public function testNotHttpsWithCustomStatusCode()
    {
        $request = $this->request->withUri(
            new Uri('http://domain.com')
        );
        $handler = function ($request) {
            throw new \Exception('Should not make it here');
        };
        $middleware = new RedirectHttpsMiddleware(307);
        $result = $middleware->process($request, new RequestHandlerCallable($handler));
        $this->assertEquals(307, $result->getStatusCode());
        $this->assertEquals('https://domain.com', $result->getHeaderLine('Location'));
    }

    public function testNotHttpsAndExceptURI()
    {
        $request = $this->request->withUri(
            new Uri('http://domain.com')
        );
        $handler = function ($request) {
            return (new Response())->write('SUCCESS');
        };
        $middleware = new RedirectHttpsMiddleware(301, ['http://domain.com']);
        $result = $middleware->process($request, new RequestHandlerCallable($handler));
        $this->assertEquals('SUCCESS', (string) $result->getBody());
    }

    public function testNotHttpsAndExceptURIForPattern()
    {
        $request = $this->request->withUri(
            new Uri('http://domain.com/foo/bar')
        );
        $handler = function ($request) {
            return (new Response())->write('SUCCESS');
        };
        $middleware = new RedirectHttpsMiddleware(301, ['http://domain.com/*']);
        $result = $middleware->process($request, new RequestHandlerCallable($handler));
        $this->assertEquals('SUCCESS', (string) $result->getBody());
    }
}
