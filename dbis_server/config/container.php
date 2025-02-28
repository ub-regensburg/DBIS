<?php

use Psr\Container\ContainerInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use App\Infrastructure\Shared\ResourceProvider;
use App\Infrastructure\Shared\DummyAuthService;
use App\Infrastructure\Shared\RZAuthToDbisAuthAdapter;
use App\Domain\Shared\AuthService;
use App\Infrastructure\Organizations\UBROrganizationRepository;
use App\Domain\Organizations\OrganizationService;
use App\Domain\Resources\ResourceService;
use App\Infrastructure\Resources\ResourceRepository;
use App\Infrastructure\Shared\CountryProvider;
use App\Domain\Collections\CollectionService;
use App\Infrastructure\Collections\CollectionRepository;
use App\Infrastructure\Shared\ContextProvider;

/*
 * All dependencies, that should be globally available, have to be initialized
 * here.
 */
return [
    'settings' => function () {
        return require __DIR__ . '/settings.php';
    },
    App::class => function (ContainerInterface $container) {
        AppFactory::setContainer($container);

        return AppFactory::create();
    },
    ResourceProvider::class => function (ContainerInterface $container) {
        return new ResourceProvider(
            $container->get('settings')['config']['translation_dir']
        );
    },
    AuthService::class => function (ContainerInterface $container) {
        return new RZAuthToDbisAuthAdapter(
            new PDO(
                $container->get('settings')['db']['dns'],
                null,
                null,
                $container->get('settings')['db']['flags']
            )
        );
    },
    OrganizationService::class => function (ContainerInterface $container) {
        $repo = new UBROrganizationRepository(
            new PDO(
                $container->get('settings')['db_ubr']['dns'],
                null,
                null,
                $container->get('settings')['db_ubr']['flags']
            ),
            $container->get('settings')['public'] . "/icons/"
        );
        return new OrganizationService($repo);
    },
    ResourceService::class => function (ContainerInterface $container) {
        return new ResourceService(new ResourceRepository(
            new PDO(
                $container->get('settings')['db']['dns'],
                null,
                null,
                $container->get('settings')['db']['flags']
            )
        ));
    },
    CollectionService::class => function (ContainerInterface $container) {
        return new CollectionService(new CollectionRepository(
            new PDO(
                $container->get('settings')['db']['dns'],
                null,
                null,
                $container->get('settings')['db']['flags']
            )
        ));
    },
    CountryProvider::class => function (ContainerInterface $container) {
        return new CountryProvider(new PDO(
            $container->get('settings')['db_ubr']['dns'],
            null,
            null,
            $container->get('settings')['db_ubr']['flags']
        ));
    },
    ContextProvider::class => function (ContainerInterface $container) {
        return new ContextProvider();
    }
];
