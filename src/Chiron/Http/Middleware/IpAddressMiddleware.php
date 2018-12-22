<?php

declare(strict_types=1);

// TODO ; regarder ici pour les header + pour récupérer l'IP : https://github.com/threenine/StopWebCrawlers/blob/master/includes/swc-core.php

//https://github.com/litphp/middleware-ip-address/blob/master/IpAddress.php

//https://github.com/akrabat/ip-address-middleware/blob/master/src/IpAddress.php
//https://github.com/middlewares/client-ip/blob/master/src/ClientIp.php
//https://github.com/oscarotero/psr7-middlewares/blob/master/src/Middleware/ClientIp.php

//https://github.com/litphp/middleware-ip-address/blob/master/IpAddress.php

//https://github.com/zendframework/zend-http/blob/master/src/PhpEnvironment/RemoteAddress.php

namespace Chiron\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class IpAddressMiddleware implements MiddlewareInterface
{
    /**
     * Enable checking of proxy headers (X-Forwarded-For to determined client IP.
     *
     * Defaults to false as only $_SERVER['REMOTE_ADDR'] is a trustworthy source
     * of IP address.
     *
     * @var bool
     */
    protected $checkProxyHeaders;

    /**
     * List of trusted proxy IP addresses.
     *
     * If not empty, then one of these IP addresses must be in $_SERVER['REMOTE_ADDR']
     * in order for the proxy headers to be looked at.
     *
     * @var array
     */
    protected $trustedProxies;

    /**
     * Name of the attribute added to the ServerRequest object.
     *
     * @var string
     */
    protected $attributeName = 'ip_address';

    /**
     * List of proxy headers inspected for the client IP address.
     *
     * @var array
     */
    protected $headersToInspect = [
        'Forwarded',
        'Forwarded-For',
        'X-Forwarded-For',
        'X-Forwarded',
        'X-Real-IP',
        'X-Cluster-Client-Ip',
        'Client-Ip',
        'CF-Connecting-IP',
    ];

    public function __construct(
        bool $checkProxyHeaders = false,
        array $trustedProxies = [],
        string $attributeName = null,
        array $headersToInspect = []
    ) {
        $this->checkProxyHeaders = $checkProxyHeaders;
        $this->trustedProxies = $trustedProxies;
        if ($attributeName) {
            $this->attributeName = $attributeName;
        }
        if (! empty($headersToInspect)) {
            $this->headersToInspect = $headersToInspect;
        }
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $ipAddress = $this->determineClientIpAddress($request);
        $request = $request->withAttribute($this->attributeName, $ipAddress);

        return $handler->handle($request);
    }

    private function determineClientIpAddress(ServerRequestInterface $request): ?string
    {
        $localIp = $this->getLocalIpAddress($request);

        if (in_array($localIp, $this->trustedProxies) && $proxiedIp = $this->getIpAddressFromProxy($request)) {
            return $proxiedIp;
        }

        return $localIp;
    }

    /*
        private function determineClientIpAddress(ServerRequestInterface $request): ?string
        {
            $localIp = $this->getLocalIpAddress($request);

            if (empty($this->trustedProxies) || !in_array($localIp, $this->trustedProxies)) {
                return $localIp;
            }

            $proxiedIp = $this->getIpAddressFromProxy($request);
            if (! empty($proxiedIp)) {
                return $proxiedIp;
            }

            return $localIp;
        }
    */

    /**
     * Returns the remote address of the request, if valid.
     *
     * @return string|null
     */
    private function getLocalIpAddress(ServerRequestInterface $request): ?string
    {
        $server = $request->getServerParams();
        $remoteAddr = $server['REMOTE_ADDR'] ?? '';
        if (static::isValidIpAddress($remoteAddr)) {
            return $remoteAddr;
        }

        return null;
    }

    /**
     * Attempt to get the IP address for a proxied client.
     *
     * @see http://tools.ietf.org/html/draft-ietf-appsawg-http-forwarded-10#section-5.2
     *
     * @param ServerRequestInterface $request
     *
     * @return null|string
     */
    private function getIpAddressFromProxy(ServerRequestInterface $request): ?string
    {
        foreach ($this->headersToInspect as $headerName) {
            $headerValue = $request->getHeaderLine($headerName);

            if (ucwords($headerName) === 'Forwarded') {
                foreach (explode(';', $headerValue) as $headerPart) {
                    //if (strtolower(substr($headerPart, 0, 4)) === 'for=') {
                    if (stripos($headerPart, 'for=') !== false) {
                        // remove the 'for=' string and double quote character to obtain a list of ip separated with comma if multiple ip are provided.
                        $headerValue = str_ireplace(['for=', '"'], '', $headerPart);
                        //$headerValue = str_ireplace('for=', '', $headerPart);
                        //$firstHeaderPart = trim(current(explode(',', $headerPart)));
                        // IPv6 addresses are written as "quoted-string". So we do a classic trim + remove the double quote character
                        //$headerValue = trim(substr($firstHeaderPart, 4), " \t\n\r\0\x0B" . "\"");
                        //$headerValue = str_replace('"', '', $headerValue);
                        break;
                    }
                }
            }

            // get the first IP in the list
            $headerValue = trim(current(explode(',', $headerValue)));
            // remove the Port value if present
            $headerValue = static::stripIpAddressPort($headerValue);

            if (static::isValidIpAddress($headerValue)) {
                return $headerValue;
            }
        }

        return null;
    }

    private static function stripIpAddressPort(string $ipAddress): string
    {
        // IPv6 format with optional port: "[2001:db8:cafe::17]:47011"
        // returns: "2001:db8:cafe::17"
        if (strpos($ipAddress, '[') !== false) {
            return preg_replace('/(^\[|\]:\d+$)/', '', $ipAddress);
        }

        // IPv4 format with optional port: "192.0.2.43:47011"
        // returns: "192.0.2.43"
        if (substr_count($ipAddress, ':') === 1) {
            return preg_replace('/:\d+$/', '', $ipAddress);
        }

        // no port found, we return the given ip value
        return $ipAddress;
    }

    private static function isValidIpAddress(string $ipAddress): bool
    {
        $options = FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6;
        // TODO : changer les options comme ci dessous + mettre à jour les tests pour ne pas utiliser des ip en 192.xxxx
        //$options  = FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
        return false !== filter_var($ipAddress, FILTER_VALIDATE_IP, $options);
    }
}
