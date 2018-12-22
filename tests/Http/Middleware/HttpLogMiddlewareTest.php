<?php

declare(strict_types=1);

namespace Chiron\Tests\Http\Middleware;

use Chiron\Http\Middleware\HttpLogMiddleware;
use Chiron\Http\Psr\Response;
use Chiron\Http\Psr\ServerRequest;
use Chiron\Http\Psr\Uri;
use Chiron\Logger;
use Chiron\Tests\Utils\RequestHandlerCallable;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

class HttpLogMiddlewareTest extends TestCase
{
    protected $logger1;

    protected $logger2;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $this->logger1 = new Logger('http_log1.log', LogLevel::INFO);
        $this->logger2 = new Logger('http_log2.log', LogLevel::INFO);
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
    }

    public function testLogRequestAndResponseWithoutDetails()
    {
        $request = new ServerRequest('GET', new Uri('/'));
        $handler = function ($request) {
            return new Response();
        };

        $middleware = new HttpLogMiddleware($this->logger1, [
            'log_request'  => true,
            'log_response' => true,
            'details'      => false,
        ]);
        $middleware->process($request, new RequestHandlerCallable($handler));
        //$log = $this->root->getChild('http_log.log')->getContent();
        $log = file_get_contents('http_log1.log');
        $this->assertNotFalse(strpos($log, 'Request: GET /'));
        $this->assertNotFalse(strpos($log, 'Response: 200 OK'));
    }

    public function testLogRequestAndResponseWithtDetails()
    {
        $request = new ServerRequest('GET', new Uri('/'));
        $handler = function ($request) {
            return new Response();
        };

        $middleware = new HttpLogMiddleware($this->logger2, [
            'log_request'  => true,
            'log_response' => true,
            'details'      => true,
        ]);
        $middleware->process($request, new RequestHandlerCallable($handler));
        //$log = $this->root->getChild('http_log.log')->getContent();
        $log = file_get_contents('http_log2.log');
        $this->assertNotFalse(strpos($log, 'Request: GET / HTTP/1.1'));
        $this->assertNotFalse(strpos($log, 'Response: HTTP/1.1 200 OK'));
    }
}
