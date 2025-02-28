<?php

use Slim\App;
use Slim\Psr7\Response;
use DI\ContainerBuilder;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/constants.php';
require __DIR__ . '/../config/settings.php';
require __DIR__ . '/../config/DotEnv.php';

// Load environment vars
loadDotEnv("/var/www/.env");

ini_set('session.cookie_secure', '1'); // Ensure session cookies are only sent over HTTPS
ini_set('session.cookie_httponly', '1'); // Make session cookies accessible only through the HTTP protocol
session_start();

$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions(__DIR__ . '/../config/container.php');
$container = $containerBuilder->build();
$app = $container->get(App::class);

// Register routes
(require __DIR__ . '/../config/routes.php')($app);

// Register middleware
(require __DIR__ . '/../config/middleware.php')($app);

$app->run();