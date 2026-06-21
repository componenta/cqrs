<?php

namespace Componenta\CQRS\Command\Factory;

use Componenta\CQRS\Command\Locator\CommandListenersLocatorInterface;
use Componenta\CQRS\Command\Middleware\EventMiddleware;
use Psr\Container\ContainerInterface;
use function Componenta\Config\env;

final class EventMiddlewareFactory
{
    public function __invoke(ContainerInterface $container): EventMiddleware
    {
        return new EventMiddleware(
            $container->get(CommandListenersLocatorInterface::class),
            env('APP_ENV', 'development') !== 'development'
        );
    }
}