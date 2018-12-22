<?php

declare(strict_types=1);

namespace Chiron\Http\Parser;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Interface for classes that parse the raw request body into a parameters array.
 */
interface RequestParserInterface
{
    /**
     * Check if the parser supports the content type.
     *
     * @return bool Whether or not the parser matches.
     */
    public function supports(string $contentType): bool;

    /**
     * Parse the body content and return a new request.
     *
     * @param ServerRequestInterface $request
     *
     * @return ServerRequestInterface
     */
    public function parse(ServerRequestInterface $request): ServerRequestInterface;
}
