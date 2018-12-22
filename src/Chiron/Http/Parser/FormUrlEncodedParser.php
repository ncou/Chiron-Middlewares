<?php

declare(strict_types=1);

namespace Chiron\Http\Parser;

use Psr\Http\Message\ServerRequestInterface;
use function parse_str;
use function preg_match;

class FormUrlEncodedParser implements RequestParserInterface
{
    public function supports(string $contentType): bool
    {
        return (bool) preg_match('#^application/x-www-form-urlencoded($|[ ;])#', $contentType);
    }

    public function parse(ServerRequestInterface $request): ServerRequestInterface
    {
        // The body could already be parsed if the ServerRequestFactory used the superglobal $_POST to initialise the parsedBody field.
        // But this parser could be used also for PUT or PATCH request who have a body.
        $parsedBody = $request->getParsedBody();
        if (! empty($parsedBody)) {
            return $request;
        }

        $rawBody = (string) $request->getBody();
        if (empty($rawBody)) {
            return $request;
        }
        parse_str($rawBody, $parsedBody);
        // TODO : ajouter une vérification si il y a eu une erreur et lever une erreur HTTP 400 si c'est le cas => https://github.com/middlewares/payload/blob/master/src/UrlEncodePayload.php#L26

        return $request->withParsedBody($parsedBody);
    }
}
