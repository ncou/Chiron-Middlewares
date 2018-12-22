<?php

declare(strict_types=1);

namespace Chiron\Http;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

//https://github.com/zendframework/zend-diactoros/blob/master/src/Response/Serializer.php
//https://github.com/zendframework/zend-diactoros/blob/master/src/Request/Serializer.php
//https://github.com/zendframework/zend-diactoros/blob/master/src/AbstractSerializer.php

//https://github.com/ringcentral/psr7/blob/master/src/functions.php#L21

//https://github.com/guzzle/psr7/blob/master/src/functions.php#L18

//https://github.com/php-http/message/blob/master/src/Formatter/FullHttpMessageFormatter.php

abstract class Serializer
{
    //private const CR  = "\r";
    private const EOL = "\r\n";

    //private const LF  = "\n";

    /**
     * Create a string representation of a response.
     *
     * @param ResponseInterface $response
     *
     * @return string
     */
    public static function responseToString(ResponseInterface $response): string
    {
        $reasonPhrase = $response->getReasonPhrase();
        $headers = self::serializeHeaders($response->getHeaders());
        $body = (string) $response->getBody();
        $format = 'HTTP/%s %d%s%s%s';
        if (! empty($headers)) {
            $headers = self::EOL . $headers;
        }
        $headers .= self::EOL . self::EOL;

        return sprintf(
            $format,
            $response->getProtocolVersion(),
            $response->getStatusCode(),
            ($reasonPhrase ? ' ' . $reasonPhrase : ''),
            $headers,
            $body
        );
    }

    /**
     * Serialize a request message to a string.
     *
     * @param RequestInterface $request
     *
     * @return string
     */
    public static function requestToString(RequestInterface $request): string
    {
        $httpMethod = $request->getMethod();
        if (empty($httpMethod)) {
            throw new \UnexpectedValueException('Object can not be serialized because HTTP method is empty');
        }
        $headers = self::serializeHeaders($request->getHeaders());
        $body = (string) $request->getBody();
        $format = '%s %s HTTP/%s%s%s';
        if (! empty($headers)) {
            $headers = self::EOL . $headers;
        }
        if (! empty($body)) {
            $headers .= self::EOL . self::EOL;
        }

        return sprintf(
            $format,
            $httpMethod,
            $request->getRequestTarget(),
            $request->getProtocolVersion(),
            $headers,
            $body
        );
    }

    /**
     * Serialize headers to string values.
     *
     * @param array $headers
     *
     * @return string
     */
    protected static function serializeHeaders(array $headers): string
    {
        $lines = [];
        foreach ($headers as $header => $values) {
            $normalized = self::filterHeader($header);
            foreach ($values as $value) {
                $lines[] = sprintf('%s: %s', $normalized, $value);
            }
        }

        return implode(self::EOL, $lines);
    }

    /**
     * Filter a header name to wordcase.
     *
     * @param string $header
     *
     * @return string
     */
    protected static function filterHeader(string $header): string
    {
        $filtered = str_replace('-', ' ', $header);
        $filtered = ucwords($filtered);

        return str_replace(' ', '-', $filtered);
    }

    /*
     * Returns the string representation of an HTTP message.
     *
     * @param MessageInterface $message Message to convert to a string.
     *
     * @return string
     */
    /*
    function str(MessageInterface $message)
    {
        if ($message instanceof RequestInterface) {
            $msg = trim($message->getMethod() . ' '
                    . $message->getRequestTarget())
                . ' HTTP/' . $message->getProtocolVersion();
            if (!$message->hasHeader('host')) {
                $msg .= "\r\nHost: " . $message->getUri()->getHost();
            }
        } elseif ($message instanceof ResponseInterface) {
            $msg = 'HTTP/' . $message->getProtocolVersion() . ' '
                . $message->getStatusCode() . ' '
                . $message->getReasonPhrase();
        } else {
            throw new \InvalidArgumentException('Unknown message type');
        }
        foreach ($message->getHeaders() as $name => $values) {
            $msg .= "\r\n{$name}: " . implode(', ', $values);
        }
        return "{$msg}\r\n\r\n" . $message->getBody();
    }*/

/*
    public function testConvertsRequestsToStrings()
    {
        $request = new Psr7\Request('PUT', 'http://foo.com/hi?123', [
            'Baz' => 'bar',
            'Qux' => 'ipsum'
        ], 'hello', '1.0');
        $this->assertEquals(
            "PUT /hi?123 HTTP/1.0\r\nHost: foo.com\r\nBaz: bar\r\nQux: ipsum\r\n\r\nhello",
            Psr7\str($request)
        );
    }*/

    /*
    public function testConvertsResponsesToStrings()
    {
        $response = new Psr7\Response(200, [
            'Baz' => 'bar',
            'Qux' => 'ipsum'
        ], 'hello', '1.0', 'FOO');
        $this->assertEquals(
            "HTTP/1.0 200 FOO\r\nBaz: bar\r\nQux: ipsum\r\n\r\nhello",
            Psr7\str($response)
        );
    }*/
}
