<?php

namespace Componenta\CQRS\Command\Factory;

use Componenta\Config\Config;
use Componenta\CQRS\Command\Locator\CommandHandlerLocator;
use Componenta\CQRS\Command\Resolver\CommandNameResolverInterface;
use Componenta\CQRS\ConfigKey;
use Psr\Container\ContainerInterface;

final class CommandHandlerLocatorFactory
{
    public function __invoke(ContainerInterface $container): CommandHandlerLocator
    {
        /** @var Config $config */
        $config = $container->get(ConfigKey::CONFIG);

        return new CommandHandlerLocator(
            $config->get(ConfigKey::COMMAND_HANDLER_MAP, []),
            $container->has(CommandNameResolverInterface::class)
                ? $container->get(CommandNameResolverInterface::class) : null,
            $container,
        );
    }
}