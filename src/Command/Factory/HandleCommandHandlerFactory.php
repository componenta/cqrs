<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Factory;

use Componenta\CQRS\Command\Locator\CommandHandlerLocatorInterface;
use Componenta\CQRS\Command\Middleware\HandleCommandHandler;
use Componenta\DI\CallableInvokerInterface;
use Componenta\CQRS\Exception\InvalidConfigurationException;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;

final class HandleCommandHandlerFactory
{
    public function __invoke(ContainerInterface $container): HandleCommandHandler
    {
        $locator = $container->get(CommandHandlerLocatorInterface::class);

        if (!$locator instanceof CommandHandlerLocatorInterface) {
            throw new InvalidConfigurationException(sprintf(
                'Container entry "%s" must implement %s.',
                CommandHandlerLocatorInterface::class,
                CommandHandlerLocatorInterface::class,
            ));
        }

        $invoker = $container->get(CallableInvokerInterface::class);

        if (!$invoker instanceof CallableInvokerInterface) {
            throw new InvalidConfigurationException(sprintf(
                'Container entry "%s" must implement %s.',
                CallableInvokerInterface::class,
                CallableInvokerInterface::class,
            ));
        }

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

        return new HandleCommandHandler($locator, $invoker, $clock);
    }
}