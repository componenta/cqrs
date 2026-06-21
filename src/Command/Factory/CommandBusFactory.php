<?php

namespace Componenta\CQRS\Command\Factory;

use Componenta\Config\Config;
use Componenta\CQRS\Command\CommandBus;
use Componenta\CQRS\Command\Middleware\HandleCommandHandler;
use Componenta\CQRS\Command\Middleware\MiddlewareInterface;
use Componenta\CQRS\ConfigKey;
use Psr\Container\ContainerInterface;

final class CommandBusFactory
{
    public function __invoke(ContainerInterface $container): CommandBus
    {
        /**
         * @var Config $config
         * @var class-string<MiddlewareInterface>[] $middlewares
         */
        $config = $container->get('config');
        $middlewares = $config->get(ConfigKey::COMMAND_MIDDLEWARES, []);

        if ($config != []) {
            $middlewares = array_map(static fn(string $entryId) => $container->get($entryId), $middlewares);
        }

        return new CommandBus($container->get(HandleCommandHandler::class), ...$middlewares);
    }
}