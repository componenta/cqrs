<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Factory;

use Componenta\CQRS\Command\Locator\CommandListenersLocator;
use Componenta\CQRS\Command\Locator\CommandListenersLocatorInterface;
use Componenta\CQRS\Command\Resolver\CommandNameResolverInterface;
use Componenta\CQRS\ConfigKey;
use Componenta\CQRS\Exception\InvalidConfigurationException;
use Psr\Container\ContainerInterface;

final class CommandListenerLocatorFactory
{
    public function __invoke(ContainerInterface $container): CommandListenersLocatorInterface
    {
        $maps = $container->get(ConfigKey::MAPS);

        if (!is_array($maps) || !is_array($maps['command_listeners'] ?? null)) {
            throw new InvalidConfigurationException(sprintf(
                'Container entry "%s" must contain array "command_listeners".',
                ConfigKey::MAPS,
            ));
        }

        $resolver = null;

        if ($container->has(CommandNameResolverInterface::class)) {
            $resolver = $container->get(CommandNameResolverInterface::class);

            if (!$resolver instanceof CommandNameResolverInterface) {
                throw new InvalidConfigurationException(sprintf(
                    'Container entry "%s" must implement %s.',
                    CommandNameResolverInterface::class,
                    CommandNameResolverInterface::class,
                ));
            }
        }

        return new CommandListenersLocator(
            $maps['command_listeners'],
            $container,
            $resolver,
        );
    }
}
