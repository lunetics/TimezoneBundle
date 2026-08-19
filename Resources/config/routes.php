<?php

declare(strict_types=1);

use Lunetics\TimezoneBundle\Controller\BrowserTimezoneController;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

$routes = new RouteCollection();
$routes->add('lunetics_timezone_browser', new Route('/_lunetics/timezone/browser', ['_controller' => BrowserTimezoneController::class], methods: ['POST']));

return $routes;
