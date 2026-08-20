<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Factory;

use Componenta\Config\Config;
use Componenta\CQRS\Command\CommandBus;
use Componenta\CQRS\Command\Middleware\HandleCommandHandler;
use Componenta\CQRS\Command\Middleware\MiddlewareInterface;
use Componenta\CQRS\Command\Middleware\OperationHandlerInterface;
use Componenta\CQRS\Command\OperationFactory;
use Componenta\CQRS\Command\OperationFactoryInterface;
use Componenta\CQRS\ConfigKey;
use LogicException;
use Psr\Container\ContainerInterface;

final class CommandBusFactory
{
    public function __invoke(ContainerInterface $container): CommandBus
    {
        $config = $container->get(ConfigKey::CONFIG);

        if (!$config instanceof Config) {
            throw new LogicException(sprintf(
                'Container entry "%s" must be a %s instance.',
                ConfigKey::CONFIG,
                Config::class,
            ));
        }

        $handler = $container->get(HandleCommandHandler::class);

        if (!$handler instanceof OperationHandlerInterface) {
            throw new LogicException(sprintf(
                'Container entry "%s" must implement %s.',
                HandleCommandHandler::class,
                OperationHandlerInterface::class,
            ));
        }

        $middlewareIds = $config->get(ConfigKey::COMMAND_MIDDLEWARES, []);

        if (!is_array($middlewareIds) || !array_is_list($middlewareIds)) {
            throw new LogicException(sprintf(
                'Configuration entry "%s" must be a list of middleware service ids.',
                ConfigKey::COMMAND_MIDDLEWARES,
            ));
        }

        $middlewares = [];

        foreach ($middlewareIds as $index => $middlewareId) {
            if (!is_string($middlewareId) || trim($middlewareId) === '') {
                throw new LogicException(sprintf(
                    'Command middleware service id at index %d must be a non-empty string.',
                    $index,
                ));
            }

            $middleware = $container->get($middlewareId);

            if (!$middleware instanceof MiddlewareInterface) {
                throw new LogicException(sprintf(
                    'Command middleware service "%s" must implement %s; got %s.',
                    $middlewareId,
                    MiddlewareInterface::class,
                    get_debug_type($middleware),
                ));
            }

            $middlewares[] = $middleware;
        }

        $operationFactory = $container->has(OperationFactoryInterface::class)
            ? $container->get(OperationFactoryInterface::class)
            : new OperationFactory();

        if (!$operationFactory instanceof OperationFactoryInterface) {
            throw new LogicException(sprintf(
                'Container entry "%s" must implement %s.',
                OperationFactoryInterface::class,
                OperationFactoryInterface::class,
            ));
        }

        return new CommandBus(
            commandHandler: $handler,
            middlewares: $middlewares,
            operationFactory: $operationFactory,
        );
    }
}
