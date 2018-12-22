<?php

declare(strict_types=1);

namespace Chiron\Tests\Http\Middleware;

use Chiron\Http\Middleware\ReferralSpamMiddleware;
use Chiron\Http\Psr\Response;
use Chiron\Http\Psr\ServerRequest;
use Chiron\Http\Psr\Uri;
use Chiron\Tests\Utils\RequestHandlerCallable;
use PHPUnit\Framework\TestCase;

class ReferralSpamMiddlewareTest extends TestCase
{
    public function referrerSpamProvider()
    {
        return [
            [false, 'http://www.0n-line.tv'],
            [false, 'http://xn--90acenikpebbdd4f6d.xn--p1ai'], // it's the puny code for the IDN : 'http://холодныйобзвон.рф'
            [false, 'http://www.холодныйобзвон.рф'],
            [false, 'http://холодныйобзвон.рф'],
            [true, 'http://youtube.com'],
        ];
    }

    /**
     * @dataProvider referrerSpamProvider
     */
    public function testReferrerSpamFromArray(bool $allowed, string $refererHeader)
    {
        $request = new ServerRequest('GET', new Uri('/'));
        $request = $request->withHeader('Referer', $refererHeader);

        $middleware = (new ReferralSpamMiddleware())->loadBadReferersListFromArray(['0n-line.tv', 'xn--90acenikpebbdd4f6d.xn--p1ai']);

        $handler = function ($request) {
            return new Response();
        };

        $response = $middleware->process($request, new RequestHandlerCallable($handler));

        if ($allowed) {
            $this->assertEquals(200, $response->getStatusCode());
        } else {
            $this->assertEquals(403, $response->getStatusCode());
        }
    }

    /**
     * @dataProvider referrerSpamProvider
     */
    public function testReferrerSpamFromFile(bool $allowed, string $refererHeader)
    {
        $request = new ServerRequest('GET', new Uri('/'));
        $request = $request->withHeader('Referer', $refererHeader);

        $middleware = (new ReferralSpamMiddleware())->loadBadReferersListFromFile(__DIR__ . '/asset/spammers.txt');
        $handler = function ($request) {
            return new Response();
        };

        $response = $middleware->process($request, new RequestHandlerCallable($handler));

        if ($allowed) {
            $this->assertEquals(200, $response->getStatusCode());
        } else {
            $this->assertEquals(403, $response->getStatusCode());
        }
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testExceptionWhenFilePathIsInvalid()
    {
        $middleware = (new ReferralSpamMiddleware())->loadBadReferersListFromFile('bad/path');
    }
}
