<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Factory;

use Componenta\CQRS\Command\Locator\CommandHandlerLocator;
use Componenta\CQRS\Command\Locator\CommandHandlerLocatorInterface;
use Componenta\CQRS\Command\Resolver\CommandNameResolverInterface;
use Componenta\CQRS\ConfigKey;
use LogicException;
use Psr\Container\ContainerInterface;

final class CommandHandlerLocatorFactory
{
    public function __invoke(ContainerInterface $container): CommandHandlerLocatorInterface
    {
        $maps = $container->get(ConfigKey::MAPS);

        if (!is_array($maps) || !is_array($maps['command_handlers'] ?? null)) {
            throw new LogicException(sprintf(
                'Container entry "%s" must contain array "command_handlers".',
                ConfigKey::MAPS,
            ));
        }

        $resolver = null;

        if ($container->has(CommandNameResolverInterface::class)) {
            $resolver = $container->get(CommandNameResolverInterface::class);

            if (!$resolver instanceof CommandNameResolverInterface) {
                throw new LogicException(sprintf(
                    'Container entry "%s" must implement %s.',
                    CommandNameResolverInterface::class,
                    CommandNameResolverInterface::class,
                ));
            }
        }

        return new CommandHandlerLocator(
            $maps['command_handlers'],
            $container,
            $resolver,
        );
    }
}
