<?php

declare(strict_types=1);

namespace Chiron\Http\Middleware;

use Chiron\Http\Psr\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * This middleware is the work of oscarotero.
 *
 * @see https://github.com/middlewares/trailing-slash/blob/master/src/TrailingSlash.php
 */
class RedirectTrailingSlashMiddleware implements MiddlewareInterface
{
    /**
     * @var bool Add or remove the slash
     */
    private $trailingSlash;

    /**
     * @var bool Returns a redirect response or not
     */
    private $redirect = false;

    /**
     * Configure whether add or remove the slash.
     */
    public function __construct(bool $trailingSlash = false)
    {
        $this->trailingSlash = $trailingSlash;
    }

    /**
     * Whether returns a 301 response to the new path.
     */
    public function redirect(bool $redirect = true): self
    {
        $this->redirect = $redirect;

        return $this;
    }

    /**
     * Process a request and return a response.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $uri = $request->getUri();
        $path = $this->normalize($uri->getPath());
        if ($this->redirect && ($uri->getPath() !== $path)) {
            // TODO : passer une responseFactory en paramétre dans le constructeur
            $response = new Response();

            return $response
                ->withStatus(301)
                ->withHeader('Location', (string) $uri->withPath($path));
        }

        return $handler->handle($request->withUri($uri->withPath($path)));
    }

    /**
     * Normalize the trailing slash.
     */
    private function normalize(string $path): string
    {
        if ($path === '') {
            return '/';
        }
        if (strlen($path) > 1) {
            if ($this->trailingSlash) {
                // check if it's not a root path or an extension path like xxx/api.json
                if (substr($path, -1) !== '/' && ! pathinfo($path, PATHINFO_EXTENSION)) {
                    return $path . '/';
                }
            } else {
                return rtrim($path, '/\\');
            }
        }

        return $path;
    }
}
