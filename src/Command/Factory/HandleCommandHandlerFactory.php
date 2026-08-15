<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Factory;

use Componenta\CQRS\Command\Locator\CommandHandlerLocatorInterface;
use Componenta\CQRS\Command\Middleware\HandleCommandHandler;
use Componenta\DI\CallableInvokerInterface;
use LogicException;
use Psr\Container\ContainerInterface;

final class HandleCommandHandlerFactory
{
    public function __invoke(ContainerInterface $container): HandleCommandHandler
    {
        $locator = $container->get(CommandHandlerLocatorInterface::class);

        if (!$locator instanceof CommandHandlerLocatorInterface) {
            throw new LogicException(sprintf(
                'Container entry "%s" must implement %s.',
                CommandHandlerLocatorInterface::class,
                CommandHandlerLocatorInterface::class,
            ));
        }

        $invoker = $container->get(CallableInvokerInterface::class);

        if (!$invoker instanceof CallableInvokerInterface) {
            throw new LogicException(sprintf(
                'Container entry "%s" must implement %s.',
                CallableInvokerInterface::class,
                CallableInvokerInterface::class,
            ));
        }

        return new HandleCommandHandler($locator, $invoker);
    }
}