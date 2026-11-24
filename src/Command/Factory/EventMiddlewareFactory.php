<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Factory;

use Componenta\CQRS\Clock\UtcClock;
use Componenta\Config\ContainerValue;
use Componenta\CQRS\Command\Locator\CommandListenersLocatorInterface;
use Componenta\CQRS\Command\Middleware\EventMiddleware;
use Componenta\CQRS\Exception\InvalidConfigurationException;
use Psr\Clock\ClockInterface;

final class EventMiddlewareFactory
{
    public function __invoke(ContainerValue $container): EventMiddleware
    {
        $clock = $container->has(ClockInterface::class)
            ? $container->get(ClockInterface::class)
            : new UtcClock();

        if (!$clock instanceof ClockInterface) {
            throw new InvalidConfigurationException(sprintf(
                'Container entry "%s" must implement %s.',
                ClockInterface::class,
                ClockInterface::class,
            ));
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
