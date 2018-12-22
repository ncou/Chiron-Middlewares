<?php

declare(strict_types=1);

namespace Chiron\Http\Parser;

use Chiron\Http\Exception\Client\BadRequestHttpException;
use Psr\Http\Message\ServerRequestInterface;
use function array_shift;
use function explode;
use function json_decode;
use function preg_match;
use function trim;

class JsonParser implements RequestParserInterface
{
    /**
     * @var bool whether to return objects in terms of associative arrays.
     */
    //public $asArray = true;

    /**
     * @var bool whether to throw a [[BadRequestHttpException]] if the body is invalid
     */
    public $throwException = false;

    public function supports(string $contentType): bool
    {
        $parts = explode(';', $contentType);
        $mime = trim(array_shift($parts));

        return (bool) preg_match('~application/([a-z.]+\+)?json~', $mime);
        //return (bool) preg_match('#[/+]json$#', $mime);

        // Regex for : 'application/json' or 'application/*+json'
        //if (preg_match('~^application/([a-z.]+\+)?json($|;)~', $mediaType)) {
        //return (bool) preg_match('#[/+]json$#', trim($mime));
    }

    /**
     * Parses a HTTP request body.
     *
     * @param string $rawBody     the raw HTTP request body.
     * @param string $contentType the content type specified for the request body.
     *
     * @throws BadRequestHttpException if the body contains invalid json and [[throwException]] is `true`.
     *
     * @return array parameters parsed from the request body
     */
    public function parse(ServerRequestInterface $request): ServerRequestInterface
    {
        $rawBody = (string) $request->getBody();
        $parsedBody = json_decode($rawBody, true);
        //$parsedBody = json_decode($rawBody, $this->asArray);
        if (! is_array($parsedBody)) {
            $parsedBody = null;

            if ($this->throwException) {
                throw new BadRequestHttpException('Error when parsing JSON request body');
            }
        }

        return $request->withParsedBody($parsedBody);
    }
}
