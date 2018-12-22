<?php

declare(strict_types=1);

namespace Chiron\Tests\Utils;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RequestHandlerCallable implements RequestHandlerInterface
{
    /**
     * @var callable
     */
    private $adaptee;

    /**
     * @param RequestHandlerInterface $adaptee
     */
    public function __construct(callable $adaptee)
    {
        $this->adaptee = $adaptee;
    }

    /**
     * Process the request using a handler.
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return call_user_func($this->adaptee, $request);
    }
}
