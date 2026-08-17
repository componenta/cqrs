<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Factory;

use Componenta\Config\ContainerValue;
use Componenta\CQRS\Command\Locator\CommandListenersLocatorInterface;
use Componenta\CQRS\Command\Middleware\EventMiddleware;

final class EventMiddlewareFactory
{
    public function __invoke(ContainerValue $container): EventMiddleware
    {
        return new EventMiddleware(
            $container->get(
                CommandListenersLocatorInterface::class,
                CommandListenersLocatorInterface::class,
            ),
        );
    }
}
