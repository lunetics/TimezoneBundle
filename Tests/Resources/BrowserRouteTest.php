<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Tests\Resources;

use Lunetics\TimezoneBundle\Controller\BrowserTimezoneController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouteCollection;

final class BrowserRouteTest extends TestCase
{
    public function testOptInRouteUsesExpectedProtocol(): void
    {
        $routes = require dirname(__DIR__, 2).'/Resources/config/routes.php';

        self::assertInstanceOf(RouteCollection::class, $routes);
        $route = $routes->get('lunetics_timezone_browser');
        self::assertNotNull($route);
        self::assertSame('/_lunetics/timezone/browser', $route->getPath());
        self::assertSame(['POST'], $route->getMethods());
        self::assertSame(BrowserTimezoneController::class, $route->getDefault('_controller'));
    }
}
