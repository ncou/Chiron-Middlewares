<?php

declare(strict_types=1);

namespace Chiron\Http\Middleware;

use Chiron\Http\Psr\Response;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class UserAgentBlockerMiddleware implements MiddlewareInterface
{
    /**
     * @var array
     */
    private $badAgents = [];

    public function loadBadAgentsListFromArray(array $badAgents): self
    {
        $this->badAgents = $badAgents;

        return $this;
    }

    public function loadBadAgentsListFromFile(string $pathFile): self
    {
        if (! is_file($pathFile)) {
            throw new InvalidArgumentException('Unable to locate the bad user-agents blacklist file.');
        }
        $this->badAgents = file($pathFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        return $this;
    }

    /**
     * Process a request and return a response.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $userAgent = $request->getHeaderLine('User-Agent');

        if ($this->isBadAgent($userAgent)) {
            // TODO : passer une responseFactory en paramétre dans le constructeur
            // TODO : créer une exception pour la code 403 et faire un throw !!!!

            // If a human comes by, don't just serve a blank page
            //echo sprintf("Access to this website has been blocked because your user-agent '%s' is suspected of spamming.", $userAgent));
            return new Response(403); // TODO : renvoyer plutot un code 451 non ?
        }

        return $handler->handle($request);
    }

    /*
     * Check if the given user-agent is listed in the bad agents array.
     *
     * @param string
     *
     * @return bool
     */
    public function isBadAgent(string $userAgent): bool
    {
        // protect the specials characters that are used in the regular expression syntax
        $badAgents = array_map(function ($value) {
            return preg_quote($value, '/');
        }, $this->badAgents);
        // create a large regex with all the values to search
        $pattern = '(' . implode('|', $badAgents) . ')';
        // search the bad bots ! (result is : 0, 1 or FALSE if there is an error)
        $result = preg_match('/' . $pattern . '/i', $userAgent);

        return (bool) $result;
    }
}
