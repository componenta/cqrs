<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Factory;

use Componenta\CQRS\Command\Locator\CommandListenersLocator;
use Componenta\CQRS\Command\Locator\CommandListenersLocatorInterface;
use Componenta\CQRS\Command\Resolver\CommandNameResolverInterface;
use Componenta\CQRS\Map\CqrsMapProviderInterface;
use LogicException;
use Psr\Container\ContainerInterface;

final class CommandListenerLocatorFactory
{
    public function __invoke(ContainerInterface $container): CommandListenersLocatorInterface
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

        return new CommandListenersLocator(
            $mapProvider,
            $container,
            $resolver,
        );
    }
}
