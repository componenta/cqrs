<?php

namespace Componenta\CQRS\Command\Factory;

use Componenta\CQRS\Command\Locator\CommandHandlerLocatorInterface;
use Componenta\CQRS\Command\Middleware\HandleCommandHandler;
use Componenta\DI\CallableInvokerInterface;
use Psr\Container\ContainerInterface;

final class HandleCommandHandlerFactory
{
    public function __invoke(ContainerInterface $container): HandleCommandHandler
    {
        return new HandleCommandHandler(
            $container->get(CommandHandlerLocatorInterface::class),
            $container->get(CallableInvokerInterface::class)
        );
    }
}