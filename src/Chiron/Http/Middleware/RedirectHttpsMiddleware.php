<?php

declare(strict_types=1);

namespace Chiron\Http\Middleware;

use Chiron\Http\Psr\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RedirectHttpsMiddleware implements MiddlewareInterface
{
    /** @var int */
    private $statusCode;

    /** @var array list of uri who need to bypass the https redirection */
    private $except = [];

    /**
     * Constructor.
     *
     * @param int $statusCode used for the redirection, by default = 301 for moved permently
     */
    // TODO : lui passer plutot un tableau de settings avec un paramétre statuscode et un tableau d'exclusion
    public function __construct(int $statusCode = 301, array $except = [])
    {
        // TODO : lever une exception si le code n'est pas compris entre 300 et 399 cad SI NOT : $this->getStatusCode() >= 300 && $this->getStatusCode() < 400
        $this->statusCode = $statusCode;
        $this->except = $except;
    }

    /**
     * Redirect to HTTPS schema if necessary.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (! $this->isHttps($request) && ! $this->inExceptArray($request)) {
            return $this->redirect($request, $this->statusCode);
        }

        return $handler->handle($request);
        // TODO : gérer le cas ou la response posséde aussi un redirect vers une "Location" qui n'est pas en https, il faudrait écraser ce header et remettre un https, exemple : https://github.com/middlewares/https/blob/master/src/Https.php#L122
    }

    /**
     * Is request Https?
     *
     * @param ServerRequestInterface $request PSR7 Request
     *
     * @return bool
     */
    protected function isHttps(ServerRequestInterface $request): bool
    {
        return $request->getUri()->getScheme() === 'https';
    }

    /**
     * Determine if the request URI matches a configured exception.
     *
     * @param ServerRequestInterface $request
     *
     * @return bool
     */
    protected function inExceptArray(ServerRequestInterface $request): bool
    {
        $value = (string) $request->getUri();

        foreach ($this->except as $except) {
            // is the except uri exactly found in the current uri ?
            /*
            if (strcasecmp($except, $value) === 0) {
                return true;
            }*/
            // else use a pattern in case the $except is a regex
            $pattern = preg_quote($except, '#');
            // Asterisks are translated into zero-or-more regular expression wildcards
            // to make it convenient to check if the strings starts with the given
            // pattern such as "library/*", making any string check convenient.
            $pattern = str_replace('\*', '.*', $pattern);
            if (preg_match('#^' . $pattern . '\z#u', $value) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Redirect to HTTPS (with code = 301 for moved permently).
     *
     * @param ServerRequestInterface $request PSR7 Request
     *
     * @return ResponseInterface
     */
    protected function redirect(ServerRequestInterface $request, int $statusCode): ResponseInterface
    {
        // TODO : passer une responseFactory en paramétre dans le constructeur
        $response = new Response();

        $uri = $request->getUri()
            ->withScheme('https')
            ->withPort(443);

        return $response
            ->withStatus($statusCode)
            ->withHeader('Location', (string) $uri);
    }

    // TODO : ajouter une méthode setStatusCode() pour configurer le code de redirection sans passer par le constructeur !!!!
// TODO : vérifier si on a vraiment besoin de cette méthode, et il faudrait la renommer en addException() ou addExcludedUri ou addExclusion
/*
    public function setExceptions($except = [])
    {
        $this->$except = $except;
    }
    */

/*
    public function getExceptions()
    {
        return $this->$except;
    }
*/
}
