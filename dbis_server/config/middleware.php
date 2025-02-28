<?php

use Slim\App;
use Selective\BasePath\BasePathMiddleware;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Slim\Middleware\MethodOverrideMiddleware;
use Fullpipe\TwigWebpackExtension\WebpackExtension as WebpackExtension;
use Symfony\Bridge\Twig\Extension\RoutingExtension as RoutingExtension;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Response;


/*
 * Slim adds middleware as concentric layers surrounding the core application.
 * Each new middleware layer surrounds any existing middleware layers. The
 * concentric structure expands outwardly as additional middleware layers are
 * added.The last middleware layer added is the first to be executed.
 */
return function (App $app) {
    require __DIR__ . '/settings.php';

    // Add parsing middleware
    $app->addBodyParsingMiddleware();
    // Add Twig middleware

    $twig = Twig::create($settings['twig']['paths'], $settings['twig']['options']);
    $twig->addExtension(new WebpackExtension(
        $settings['public'] . '/dist/manifest.json',
        $settings['public']
    ));

    $container = $app->getContainer();
    $container->set('twig', $twig);

    $app->add(TwigMiddleware::create($app, $twig));

    // Add routing middleware
    $app->addRoutingMiddleware();
    // Add MethodOverride middleware
    $methodOverrideMiddleware = new MethodOverrideMiddleware();
    $app->add($methodOverrideMiddleware);

    // Ideally the DoumentRoot of your production server points directly to the public/ directory.
    // The BasePathMiddleware detects and sets the base path into the Slim app instance.
    $app->add(new BasePathMiddleware($app));
};
