<?php

declare(strict_types=1);

namespace Chiron\Tests\Http\Middleware;

use Chiron\Http\Middleware\IpAddressMiddleware;
use Chiron\Http\Psr\Response;
use Chiron\Http\Psr\ServerRequest;
use Chiron\Http\Psr\Uri;
use Chiron\Tests\Utils\RequestHandlerCallable;
use PHPUnit\Framework\TestCase;

class IpAdressMiddlewareTest extends TestCase
{
    public function testIpSetByRemoteAddr()
    {
        $middleware = new IPAddressMiddleware(false, [], 'IP');

        $request = new ServerRequest('GET', new Uri('/'), [], null, '1.1', ['REMOTE_ADDR'            => '192.168.1.1']);

        $handler = function ($request) use (&$ipAddress) {
            // simply store the "ip_address" attribute in to the referenced $ipAddress
            $ipAddress = $request->getAttribute('IP');

            return new Response();
        };
        $middleware->process($request, new RequestHandlerCallable($handler));
        $this->assertSame('192.168.1.1', $ipAddress);
    }

    public function testIpIsNullIfMissing()
    {
        $middleware = new IPAddressMiddleware();
        $request = new ServerRequest('GET', new Uri('/'), [], null, '1.1', ['REMOTE_ADDR'            => '192.168.1.1']);

        $ipAddress = '123';
        $handler = function ($request) use (&$ipAddress) {
            // simply store the "ip_address" attribute in to the referenced $ipAddress
            $ipAddress = $request->getAttribute('ip_address');

            return new Response();
        };
        $middleware->process($request, new RequestHandlerCallable($handler));
        $this->assertNull($ipAddress);
    }

    public function testXForwardedForIp()
    {
        $middleware = new IPAddressMiddleware(true, ['192.168.1.1']);

        $request = new ServerRequest('GET', new Uri('/'), [], null, '1.1', ['REMOTE_ADDR'            => '192.168.1.1',
            'HTTP_X_FORWARDED_FOR'                                                                   => '192.168.1.3, 192.168.1.2, 192.168.1.1', ]);

        $ipAddress = '123';
        $handler = function ($request) use (&$ipAddress) {
            // simply store the "ip_address" attribute in to the referenced $ipAddress
            $ipAddress = $request->getAttribute('ip_address');

            return new Response();
        };
        $middleware->process($request, new RequestHandlerCallable($handler));
        $this->assertSame('192.168.1.3', $ipAddress);
    }

    public function testProxyIpIsIgnored()
    {
        $middleware = new IPAddressMiddleware();

        $request = new ServerRequest('GET', new Uri('/'), [], null, '1.1', ['REMOTE_ADDR'            => '192.168.0.1',
            'HTTP_X_FORWARDED_FOR'                                                                   => '192.168.1.3, 192.168.1.2, 192.168.1.1', ]);

        $ipAddress = '123';
        $handler = function ($request) use (&$ipAddress) {
            // simply store the "ip_address" attribute in to the referenced $ipAddress
            $ipAddress = $request->getAttribute('ip_address');

            return new Response();
        };
        $middleware->process($request, new RequestHandlerCallable($handler));
        $this->assertSame('192.168.0.1', $ipAddress);
    }

    public function testHttpClientIp()
    {
        $middleware = new IPAddressMiddleware(true, ['192.168.1.1']);

        $request = new ServerRequest('GET', new Uri('/'), [], null, '1.1', ['REMOTE_ADDR'            => '192.168.1.1',
            'HTTP_CLIENT_IP'                                                                         => '192.168.1.3', ]);

        $ipAddress = '123';
        $handler = function ($request) use (&$ipAddress) {
            // simply store the "ip_address" attribute in to the referenced $ipAddress
            $ipAddress = $request->getAttribute('ip_address');

            return new Response();
        };
        $middleware->process($request, new RequestHandlerCallable($handler));
        $this->assertSame('192.168.1.3', $ipAddress);
    }

    public function testXForwardedForIpV6()
    {
        $middleware = new IPAddressMiddleware(true, ['192.168.1.1']);

        $request = new ServerRequest('GET', new Uri('/'), [], null, '1.1', ['REMOTE_ADDR'            => '192.168.1.1',
            'HTTP_X_FORWARDED_FOR'                                                                   => '001:DB8::21f:5bff:febf:ce22:8a2e', ]);

        $ipAddress = '123';
        $handler = function ($request) use (&$ipAddress) {
            // simply store the "ip_address" attribute in to the referenced $ipAddress
            $ipAddress = $request->getAttribute('ip_address');

            return new Response();
        };
        $middleware->process($request, new RequestHandlerCallable($handler));
        $this->assertSame('001:DB8::21f:5bff:febf:ce22:8a2e', $ipAddress);
    }

    public function testXForwardedForIpV6WithPort()
    {
        $middleware = new IPAddressMiddleware(true, ['192.168.1.1']);

        $request = new ServerRequest('GET', new Uri('/'), [], null, '1.1', ['REMOTE_ADDR'            => '192.168.1.1',
            'HTTP_X_FORWARDED_FOR'                                                                   => '[001:DB8::21f:5bff:febf:ce22:8a2e]:666', ]);

        $ipAddress = '123';
        $handler = function ($request) use (&$ipAddress) {
            // simply store the "ip_address" attribute in to the referenced $ipAddress
            $ipAddress = $request->getAttribute('ip_address');

            return new Response();
        };
        $middleware->process($request, new RequestHandlerCallable($handler));
        $this->assertSame('001:DB8::21f:5bff:febf:ce22:8a2e', $ipAddress);
    }

    public function testXForwardedForIpV4()
    {
        $middleware = new IPAddressMiddleware(true, ['192.168.1.1']);

        $request = new ServerRequest('GET', new Uri('/'), [], null, '1.1', ['REMOTE_ADDR'            => '192.168.1.1',
            'HTTP_X_FORWARDED_FOR'                                                                   => '125.125.125.125', ]);

        $ipAddress = '123';
        $handler = function ($request) use (&$ipAddress) {
            // simply store the "ip_address" attribute in to the referenced $ipAddress
            $ipAddress = $request->getAttribute('ip_address');

            return new Response();
        };
        $middleware->process($request, new RequestHandlerCallable($handler));
        $this->assertSame('125.125.125.125', $ipAddress);
    }

    public function testXForwardedForIpV4WithPort()
    {
        $middleware = new IPAddressMiddleware(true, ['192.168.1.1']);

        $request = new ServerRequest('GET', new Uri('/'), [], null, '1.1', ['REMOTE_ADDR'            => '192.168.1.1',
            'HTTP_X_FORWARDED_FOR'                                                                   => '125.125.125.125:666', ]);

        $ipAddress = '123';
        $handler = function ($request) use (&$ipAddress) {
            // simply store the "ip_address" attribute in to the referenced $ipAddress
            $ipAddress = $request->getAttribute('ip_address');

            return new Response();
        };
        $middleware->process($request, new RequestHandlerCallable($handler));
        $this->assertSame('125.125.125.125', $ipAddress);
    }

    public function testXForwardedForIpV6Localhost()
    {
        $middleware = new IPAddressMiddleware(true, ['192.168.1.1']);

        $request = new ServerRequest('GET', new Uri('/'), [], null, '1.1', ['REMOTE_ADDR'            => '192.168.1.1',
            'HTTP_X_FORWARDED_FOR'                                                                   => '::1', ]);

        $ipAddress = '123';
        $handler = function ($request) use (&$ipAddress) {
            // simply store the "ip_address" attribute in to the referenced $ipAddress
            $ipAddress = $request->getAttribute('ip_address');

            return new Response();
        };
        $middleware->process($request, new RequestHandlerCallable($handler));
        $this->assertSame('::1', $ipAddress);
    }

    public function testXForwardedForWithInvalidIp()
    {
        $middleware = new IPAddressMiddleware(true, ['192.168.1.1']);

        $request = new ServerRequest('GET', new Uri('/'), [], null, '1.1', ['REMOTE_ADDR'            => '192.168.1.1',
            'HTTP_X_FORWARDED_FOR'                                                                   => 'foo-bar', ]);

        $ipAddress = '123';
        $handler = function ($request) use (&$ipAddress) {
            // simply store the "ip_address" attribute in to the referenced $ipAddress
            $ipAddress = $request->getAttribute('ip_address');

            return new Response();
        };
        $middleware->process($request, new RequestHandlerCallable($handler));
        $this->assertSame('192.168.1.1', $ipAddress);
    }

    public function testXForwardedForIpWithTrustedProxy()
    {
        $middleware = new IPAddressMiddleware(true, ['192.168.0.1', '192.168.0.2']);

        $request = new ServerRequest('GET', new Uri('/'), [], null, '1.1', ['REMOTE_ADDR'            => '192.168.0.2',
            'HTTP_X_FORWARDED_FOR'                                                                   => '192.168.1.3, 192.168.1.2, 192.168.1.1', ]);

        $ipAddress = '123';
        $handler = function ($request) use (&$ipAddress) {
            // simply store the "ip_address" attribute in to the referenced $ipAddress
            $ipAddress = $request->getAttribute('ip_address');

            return new Response();
        };
        $middleware->process($request, new RequestHandlerCallable($handler));
        $this->assertSame('192.168.1.3', $ipAddress);
    }

    public function testXForwardedForIpWithUntrustedProxy()
    {
        $middleware = new IPAddressMiddleware(true, ['192.168.0.1']);

        $request = new ServerRequest('GET', new Uri('/'), [], null, '1.1', ['REMOTE_ADDR'            => '192.168.0.2',
            'HTTP_X_FORWARDED_FOR'                                                                   => '192.168.1.3, 192.168.1.2, 192.168.1.1', ]);

        $ipAddress = '123';
        $handler = function ($request) use (&$ipAddress) {
            // simply store the "ip_address" attribute in to the referenced $ipAddress
            $ipAddress = $request->getAttribute('ip_address');

            return new Response();
        };
        $middleware->process($request, new RequestHandlerCallable($handler));
        $this->assertSame('192.168.0.2', $ipAddress);
    }

    public function testForwardedWithMultipleFor()
    {
        $middleware = new IPAddressMiddleware(true, ['192.168.1.1']);

        $request = new ServerRequest('GET', new Uri('/'), [], null, '1.1', ['REMOTE_ADDR'            => '192.168.1.1',
            'HTTP_FORWARDED'                                                                         => 'for=192.0.2.43, for=198.51.100.17;by=203.0.113.60;proto=http;host=example.com', ]);

        $ipAddress = '123';
        $handler = function ($request) use (&$ipAddress) {
            // simply store the "ip_address" attribute in to the referenced $ipAddress
            $ipAddress = $request->getAttribute('ip_address');

            return new Response();
        };
        $middleware->process($request, new RequestHandlerCallable($handler));
        $this->assertSame('192.0.2.43', $ipAddress);
    }

    public function testForwardedWithAllOptions()
    {
        $middleware = new IPAddressMiddleware(true, ['192.168.1.1']);

        $request = new ServerRequest('GET', new Uri('/'), [], null, '1.1', ['REMOTE_ADDR'            => '192.168.1.1',
            'HTTP_FORWARDED'                                                                         => 'for=192.0.2.60; proto=http;by=203.0.113.43; host=_hiddenProxy, for=192.0.2.61', ]);

        $ipAddress = '123';
        $handler = function ($request) use (&$ipAddress) {
            // simply store the "ip_address" attribute in to the referenced $ipAddress
            $ipAddress = $request->getAttribute('ip_address');

            return new Response();
        };
        $middleware->process($request, new RequestHandlerCallable($handler));
        $this->assertSame('192.0.2.60', $ipAddress);
    }

    public function testForwardedWithWithIpV6WithPort()
    {
        $middleware = new IPAddressMiddleware(true, ['192.168.1.1']);

        $request = new ServerRequest('GET', new Uri('/'), [], null, '1.1', ['REMOTE_ADDR'            => '192.168.1.1',
            'HTTP_FORWARDED'                                                                         => 'For="[2001:db8:cafe::17]:4711", for=_internalProxy', ]);

        $ipAddress = '123';
        $handler = function ($request) use (&$ipAddress) {
            // simply store the "ip_address" attribute in to the referenced $ipAddress
            $ipAddress = $request->getAttribute('ip_address');

            return new Response();
        };
        $middleware->process($request, new RequestHandlerCallable($handler));
        $this->assertSame('2001:db8:cafe::17', $ipAddress);
    }

    public function testForwardedWithWithIpV4WithPort()
    {
        $middleware = new IPAddressMiddleware(true, ['192.168.1.1']);

        $request = new ServerRequest('GET', new Uri('/'), [], null, '1.1', ['REMOTE_ADDR'            => '192.168.1.1',
            'HTTP_FORWARDED'                                                                         => 'For="125.125.125.125:8181", for=_internalProxy', ]);

        $ipAddress = '123';
        $handler = function ($request) use (&$ipAddress) {
            // simply store the "ip_address" attribute in to the referenced $ipAddress
            $ipAddress = $request->getAttribute('ip_address');

            return new Response();
        };
        $middleware->process($request, new RequestHandlerCallable($handler));
        $this->assertSame('125.125.125.125', $ipAddress);
    }

    public function testForwardedWithWithIpV6Localhost()
    {
        $middleware = new IPAddressMiddleware(true, ['192.168.1.1']);

        $request = new ServerRequest('GET', new Uri('/'), [], null, '1.1', ['REMOTE_ADDR'            => '192.168.1.1',
            'HTTP_FORWARDED'                                                                         => 'For="::1", for=_internalProxy', ]);

        $ipAddress = '123';
        $handler = function ($request) use (&$ipAddress) {
            // simply store the "ip_address" attribute in to the referenced $ipAddress
            $ipAddress = $request->getAttribute('ip_address');

            return new Response();
        };
        $middleware->process($request, new RequestHandlerCallable($handler));
        $this->assertSame('::1', $ipAddress);
    }

    public function testCustomHeader()
    {
        $headersToInspect = [
            'Foo-Bar',
        ];
        $middleware = new IPAddressMiddleware(true, ['192.168.0.1'], null, $headersToInspect);

        $request = new ServerRequest('GET', new Uri('/'), [], null, '1.1', ['REMOTE_ADDR'            => '192.168.0.1']);

        $request = $request->withAddedHeader('Foo-Bar', '192.168.1.3');
        $ipAddress = '123';
        $handler = function ($request) use (&$ipAddress) {
            // simply store the "ip_address" attribute in to the referenced $ipAddress
            $ipAddress = $request->getAttribute('ip_address');

            return new Response();
        };
        $middleware->process($request, new RequestHandlerCallable($handler));
        $this->assertSame('192.168.1.3', $ipAddress);
    }
}
