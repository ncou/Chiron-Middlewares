<?php

declare(strict_types=1);

namespace Tests\Http;

use Chiron\Http\Psr\Response;
use Chiron\Http\Psr\ServerRequest;
use Chiron\Http\Psr\Stream;
use Chiron\Http\Psr\Uri;
use Chiron\Http\Serializer;
use PHPUnit\Framework\TestCase;

class SerializerTest extends TestCase
{
    protected function setUp()
    {
    }

    protected function tearDown()
    {
    }

    // ********** RESPONSE *********
    public function testSerializesBasicResponse()
    {
        $response = (new Response())
            ->withStatus(200)
            ->withAddedHeader('Content-Type', 'text/plain')
            ->withAddedHeader('X-Foo-Bar', 'Baz');
        $response->getBody()->write('Content!');
        $message = Serializer::responseToString($response);
        $this->assertSame(
            "HTTP/1.1 200 OK\r\nContent-Type: text/plain\r\nX-Foo-Bar: Baz\r\n\r\nContent!",
            $message
        );
    }

    public function testSerializesResponseWithoutBodyCorrectly()
    {
        $response = (new Response())
            ->withStatus(200)
            ->withAddedHeader('Content-Type', 'text/plain');
        $message = Serializer::responseToString($response);
        $this->assertSame(
            "HTTP/1.1 200 OK\r\nContent-Type: text/plain\r\n\r\n",
            $message
        );
    }

    public function testSerializesResponseWithMultipleHeadersCorrectly()
    {
        $response = (new Response())
            ->withStatus(204)
            ->withAddedHeader('X-Foo-Bar', 'Baz')
            ->withAddedHeader('X-Foo-Bar', 'Bat');
        $message = Serializer::responseToString($response);
        $this->assertContains('X-Foo-Bar: Baz', $message);
        $this->assertContains('X-Foo-Bar: Bat', $message);
    }

    public function testOmitsReasonPhraseFromStatusLineIfEmpty()
    {
        $response = (new Response())
            ->withStatus(299)
            ->withAddedHeader('X-Foo-Bar', 'Baz');
        $response->getBody()->write('Content!');
        $message = Serializer::responseToString($response);
        $this->assertContains("HTTP/1.1 299\r\n", $message);
    }

    // ********** REQUEST *********
    public function testSerializesBasicRequest()
    {
        $request = (new ServerRequest('GET', new Uri('http://example.com/foo/bar?baz=bat')))
            ->withAddedHeader('Accept', 'text/html');
        $message = Serializer::requestToString($request);
        $this->assertSame(
            "GET /foo/bar?baz=bat HTTP/1.1\r\nHost: example.com\r\nAccept: text/html",
            $message
        );
    }

    public function testSerializesRequestWithBody()
    {
        $body = json_encode(['test' => 'value']);
        $stream = new Stream(fopen('php://memory', 'wb+'));
        $stream->write($body);
        $request = (new ServerRequest('POST', new Uri('http://example.com/foo/bar')))
            ->withAddedHeader('Accept', 'application/json')
            ->withAddedHeader('Content-Type', 'application/json')
            ->withBody($stream);
        $message = Serializer::requestToString($request);
        $this->assertContains("POST /foo/bar HTTP/1.1\r\n", $message);
        $this->assertContains("\r\n\r\n" . $body, $message);
    }

    public function testSerializesRequestWithMultipleHeadersCorrectly()
    {
        $request = (new ServerRequest('GET', new Uri('http://example.com/foo/bar?baz=bat')))
            ->withAddedHeader('X-Foo-Bar', 'Baz')
            ->withAddedHeader('X-Foo-Bar', 'Bat');
        $message = Serializer::requestToString($request);
        $this->assertContains('X-Foo-Bar: Baz', $message);
        $this->assertContains('X-Foo-Bar: Bat', $message);
    }
}
