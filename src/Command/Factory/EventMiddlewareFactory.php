<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Factory;

use Componenta\Config\ContainerValue;
use Componenta\CQRS\Command\Locator\CommandListenersLocatorInterface;
use Componenta\CQRS\Command\Middleware\EventMiddleware;
use Componenta\CQRS\Exception\InvalidConfigurationException;
use Psr\Clock\ClockInterface;

final class EventMiddlewareFactory
{
    public function __invoke(ContainerValue $container): EventMiddleware
    {
        $clock = null;
        if ($container->has(ClockInterface::class)) {
            $clock = $container->get(ClockInterface::class);
            if (!$clock instanceof ClockInterface) {
                throw new InvalidConfigurationException(sprintf(
                    'Container entry "%s" must implement %s.',
                    ClockInterface::class,
                    ClockInterface::class,
                ));
            }
        }

        return new EventMiddleware(
            $container->get(
                CommandListenersLocatorInterface::class,
                CommandListenersLocatorInterface::class,
            ),
            clock: $clock,
        );
    }
}
