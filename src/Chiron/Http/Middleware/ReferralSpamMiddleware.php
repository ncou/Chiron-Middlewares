<?php

declare(strict_types=1);

namespace Chiron\Http\Middleware;

use Chiron\Http\Psr\Response;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

// TODO : récupérer la liste ici : https://github.com/nabble/semalt-blocker
//https://github.com/mitchellkrogza/apache-ultimate-bad-bot-blocker/blob/master/_htaccess_versions/htaccess-mod_rewrite.txt

/*
https://github.com/DougSisk/Laravel-BlockReferralSpam/blob/master/src/Middleware/BlockReferralSpam.php
https://github.com/middlewares/referrer-spam/blob/master/src/ReferrerSpam.php
https://github.com/ARCommunications/Block-Referral-Spam/blob/master/blocker.php
https://github.com/rrodrigonuez/WP-Block-Referrer-Spam/blob/master/wp-block-referrer-spam/controllers/wpbrs-controller-blocker.php
https://github.com/ARCANEDEV/SpamBlocker/blob/master/src/Http/Middleware/BlockReferralSpam.php   +   https://github.com/ARCANEDEV/SpamBlocker/blob/master/src/SpamBlocker.php
https://github.com/wpmaintainer/referer-spam-blocker/blob/master/lib/referer-spam-blocker.php
*/

class ReferralSpamMiddleware implements MiddlewareInterface
{
    /**
     * @var array
     */
    private $badReferers = [];

    public function loadBadReferersListFromArray(array $badReferers): self
    {
        $this->badReferers = $badReferers;

        return $this;
    }

    public function loadBadReferersListFromFile(string $pathFile): self
    {
        if (! is_file($pathFile)) {
            throw new InvalidArgumentException('Unable to locate the referral spam blacklist file.');
        }
        $this->badReferers = file($pathFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        return $this;
    }

    /**
     * Process a request and return a response.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->hasHeader('Referer')) {
            $referer = $request->getHeaderLine('Referer');
            $domain = $this->getDomainFromUrl($referer);

            if (in_array($domain, $this->badReferers)) {
                // TODO : passer une responseFactory en paramétre dans le constructeur
                // TODO : créer une exception pour la code 403 et faire un throw !!!!

                // If a human comes by, don't just serve a blank page
                //echo sprintf("Access to this website has been blocked because your referral '%s' is suspected of spamming.", $domain));
                return new Response(403); // TODO : renvoyer plutot un code 451 non ?
            }
        }

        return $handler->handle($request);
    }

    private function getDomainFromUrl(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        // Strip the 'www.' (SubDomain) to get only the Domain name
        //$domain = (substr(strtolower($host), 0, 4) === 'www.') ? substr($host, 4) : $host;
        $domain = preg_replace('/^www\./i', '', $host);
        // Encode the international domain name as punycode
        $domain = idn_to_ascii($domain);
        // Sanitize the output result (just in case)
        $domain = filter_var($domain, FILTER_SANITIZE_URL);

        return $domain;
    }
}
