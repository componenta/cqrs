<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Factory;

use Componenta\Config\Config;
use Componenta\CQRS\Command\Locator\CommandListenersLocatorInterface;
use Componenta\CQRS\Command\Middleware\EventMiddleware;
use Componenta\CQRS\ConfigKey;
use LogicException;
use Psr\Container\ContainerInterface;

final class EventMiddlewareFactory
{
    public function __invoke(ContainerInterface $container): EventMiddleware
    {
        $config = $container->get(ConfigKey::CONFIG);

        if (!$config instanceof Config) {
            throw new LogicException(sprintf(
                'Container entry "%s" must be a %s instance.',
                ConfigKey::CONFIG,
                Config::class,
            ));
        }

        $locator = $container->get(CommandListenersLocatorInterface::class);

        if (!$locator instanceof CommandListenersLocatorInterface) {
            throw new LogicException(sprintf(
                'Container entry "%s" must implement %s.',
                CommandListenersLocatorInterface::class,
                CommandListenersLocatorInterface::class,
            ));
        }

        return new EventMiddleware(
            $locator,
            suppressExceptions: $config->environment?->string(
                'APP_ENV',
                'development',
            ) === 'production',
        );
    }
}
