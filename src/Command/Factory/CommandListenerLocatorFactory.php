<?php

namespace Componenta\CQRS\Command\Factory;

use Componenta\Config\Config;
use Componenta\CQRS\Command\Locator\CommandListenersLocator;
use Componenta\CQRS\Command\Resolver\CommandNameResolverInterface;
use Componenta\CQRS\ConfigKey;
use Psr\Container\ContainerInterface;

final class CommandListenerLocatorFactory
{
    public function __invoke(ContainerInterface $container): CommandListenersLocator
    {
        /** @var Config $config */
        $config = $container->get(ConfigKey::CONFIG);

        return new CommandListenersLocator(
            $config->get(ConfigKey::COMMAND_LISTENER_MAP, []),
            $container->has(CommandNameResolverInterface::class)
                ? $container->get(CommandNameResolverInterface::class) : null,
            $container,
        );
    }
}