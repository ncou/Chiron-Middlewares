<?php

declare(strict_types=1);

namespace Chiron\Http\Middleware;

use Chiron\Http\Serializer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class HttpLogMiddleware implements MiddlewareInterface
{
    private $logger;

    private $options;

    /**
     * HttpLog constructor.
     *
     * @param LoggerInterface $logger
     * @param array           $options
     */
    public function __construct(LoggerInterface $logger, $options = [])
    {
        $this->logger = $logger;
        $this->options = array_merge([
            'level'        => LogLevel::INFO,
            'log_request'  => true,
            'log_response' => true,
            'details'      => false,
        ], $options);
    }

    /**
     * @param ServerRequestInterface  $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        if ($this->options['log_request']) {
            $requestMessage = $this->generateRequestLog($request, $response);
            $this->logger->log($this->options['level'], sprintf('Request: %s', $requestMessage));
        }
        if ($this->options['log_response']) {
            $responseMessage = $this->generateResponseLog($request, $response);
            $this->logger->log($this->options['level'], sprintf('Response: %s', $responseMessage));
        }

        return $response;
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     *
     * @return string
     */
    private function generateRequestLog(ServerRequestInterface $request, ResponseInterface $response)
    {
        if ($this->options['details']) {
            return Serializer::requestToString($request);
        }
        $msg = sprintf(
            '%s %s',
            $request->getMethod(),
            $request->getRequestTarget()
        );
        if ($request->hasHeader('X-Request-Id')) {
            $msg .= ' RequestId: ' . $request->getHeader('X-Request-Id')[0];
        } elseif ($response->hasHeader('X-Request-Id')) {
            $msg .= ' RequestId: ' . $response->getHeader('X-Request-Id')[0];
        }

        return $msg;
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     *
     * @return string
     */
    private function generateResponseLog(ServerRequestInterface $request, ResponseInterface $response)
    {
        if ($this->options['details']) {
            return Serializer::responseToString($response);
        }
        $reasonPhrase = $response->getReasonPhrase();
        $msg = sprintf(
            '%s %s',
            $response->getStatusCode(),
            ($reasonPhrase ? $reasonPhrase : '')
        );
        if ($response->hasHeader('X-Request-Id')) {
            $msg .= ' RequestId: ' . $response->getHeader('X-Request-Id')[0];
        } elseif ($request->hasHeader('X-Request-Id')) {
            $msg .= ' RequestId: ' . $request->getHeader('X-Request-Id')[0];
        }
        if ($response->hasHeader('X-Response-Time')) {
            $msg .= ' ResponseTime: ' . $response->getHeader('X-Response-Time')[0];
        }

        return $msg;
    }
}
