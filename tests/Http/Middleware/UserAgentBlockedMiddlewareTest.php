<?php

declare(strict_types=1);

namespace Chiron\Tests\Http\Middleware;

use Chiron\Http\Middleware\UserAgentBlockerMiddleware;
use Chiron\Http\Psr\Response;
use Chiron\Http\Psr\ServerRequest;
use Chiron\Http\Psr\Uri;
use Chiron\Tests\Utils\RequestHandlerCallable;
use PHPUnit\Framework\TestCase;

class UserAgentBlockedMiddlewareTest extends TestCase
{
    public function badAgentsProvider()
    {
        return [
            //360Spider
            [false, 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.1 (KHTML, like Gecko) Chrome/21.0.1180.89 Safari/537.1; 360Spider'],
            [false, 'Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; Trident/5.0); 360Spider(compatible; HaosouSpider; http://www.haosou.com/help/help_3_2.html)'],
            [false, 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.1 (KHTML, like Gecko) Chrome/21.0.1180.89 Safari/537.1;padding360Spider/1.0::padding'],
            //80legs
            [false, 'Mozilla/5.0 (compatible; 008/0.83; http://www.80legs.com/webcrawler.html) Gecko/2008032620'],
            [false, 'Mozilla/5.0 (compatible; 008/0.83; http://www.80LEGS.com/webcrawler.html) Gecko/2008032620'],
            // Battleztar Bazinga
            [false, 'Mozilla/5.0 (compatible; 008/0.83; Battleztar Bazinga;padding'],
            // good bot (GoogleBot)
            [true, 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'],
            [true, 'Googlebot-Image/1.0'],
        ];
    }

    /**
     * @dataProvider badAgentsProvider
     */
    public function testBlockUserAgentFromArray(bool $allowed, string $userAgent)
    {
        $request = new ServerRequest('GET', new Uri('/'));
        $request = $request->withHeader('User-Agent', $userAgent);

        $middleware = (new UserAgentBlockerMiddleware())->loadBadAgentsListFromArray(['360Spider', '80legs', 'Battleztar Bazinga']);

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
     * @dataProvider badAgentsProvider
     */
    public function testBlockUserAgentFromFile(bool $allowed, string $userAgent)
    {
        $request = new ServerRequest('GET', new Uri('/'));
        $request = $request->withHeader('User-Agent', $userAgent);

        $middleware = (new UserAgentBlockerMiddleware())->loadBadAgentsListFromFile(__DIR__ . '/asset/badbots.txt');
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
        $middleware = (new UserAgentBlockerMiddleware())->loadBadAgentsListFromFile('bad/path');
    }
}
