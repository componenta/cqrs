<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Factory;

use Componenta\CQRS\Command\Locator\CommandHandlerLocator;
use Componenta\CQRS\Command\Locator\CommandHandlerLocatorInterface;
use Componenta\CQRS\Command\Resolver\CommandNameResolverInterface;
use Componenta\CQRS\Map\CqrsMapProviderInterface;
use LogicException;
use Psr\Container\ContainerInterface;

final class CommandHandlerLocatorFactory
{
    public function __invoke(ContainerInterface $container): CommandHandlerLocatorInterface
    {
        $mapProvider = $container->get(CqrsMapProviderInterface::class);

        if (!$mapProvider instanceof CqrsMapProviderInterface) {
            throw new LogicException(sprintf(
                'Container entry "%s" must implement %s.',
                CqrsMapProviderInterface::class,
                CqrsMapProviderInterface::class,
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
            $mapProvider,
            $container,
            $resolver,
        );
    }
}
