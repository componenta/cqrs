<?php

declare(strict_types=1);

use Componenta\Config\ConfigKey as DependencyConfigKey;
use Componenta\CQRS\Command\Factory\CommandHandlerLocatorFactory;
use Componenta\CQRS\Command\Factory\CommandListenerLocatorFactory;
use Componenta\CQRS\Command\Factory\CommandMetadataProviderFactory;
use Componenta\CQRS\Command\Locator\CommandHandlerLocatorInterface;
use Componenta\CQRS\Command\Locator\CommandListenersLocatorInterface;
use Componenta\CQRS\Command\Metadata\CommandMetadataProviderInterface;
use Componenta\CQRS\ConfigKey;
use Componenta\CQRS\ConfigProvider;
use Componenta\CQRS\Map\CqrsMapProviderInterface;
use Componenta\CQRS\Map\Factory\ConfigCqrsMapProviderFactory;
use Componenta\CQRS\Query\Factory\QueryHandlerLocatorFactory;
use Componenta\CQRS\Query\Locator\QueryHandlerLocatorInterface;

it('registers map-backed CQRS runtime services without aliases or empty defaults', function (): void {
    $config = (new ConfigProvider())();

    expect($config)->not->toHaveKey(ConfigKey::COMMAND_MIDDLEWARES)
        ->and($config)->not->toHaveKey(ConfigKey::QUERY_MIDDLEWARES)
        ->and($config[DependencyConfigKey::DEPENDENCIES][DependencyConfigKey::FACTORIES])
        ->toMatchArray([
            CqrsMapProviderInterface::class => ConfigCqrsMapProviderFactory::class,
            QueryHandlerLocatorInterface::class => QueryHandlerLocatorFactory::class,
            CommandHandlerLocatorInterface::class => CommandHandlerLocatorFactory::class,
            CommandListenersLocatorInterface::class => CommandListenerLocatorFactory::class,
            CommandMetadataProviderInterface::class => CommandMetadataProviderFactory::class,
        ])
        ->and($config[DependencyConfigKey::DEPENDENCIES][DependencyConfigKey::ALIASES] ?? [])
        ->toBe([])
        ->and(defined(ConfigKey::class . '::COMMAND_HANDLER_MAP'))->toBeFalse()
        ->and(defined(ConfigKey::class . '::QUERY_HANDLER_MAP'))->toBeFalse()
        ->and(defined(ConfigKey::class . '::COMMAND_LISTENER_MAP'))->toBeFalse()
        ->and(defined(ConfigKey::class . '::COMMAND_ATTRIBUTE_MAP'))->toBeFalse()
        ->and(defined(ConfigKey::class . '::COMPILED_MAPS'))->toBeFalse();
});
