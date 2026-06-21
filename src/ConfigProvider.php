<?php

declare(strict_types=1);

namespace Componenta\CQRS;

use Componenta\CQRS\Command\CommandBusInterface;
use Componenta\CQRS\Command\Metadata\CommandAttributeProviderInterface;
use Componenta\CQRS\Command\Locator\CommandHandlerLocator;
use Componenta\CQRS\Command\Locator\CommandHandlerLocatorInterface;
use Componenta\CQRS\Command\Locator\CommandListenersLocator;
use Componenta\CQRS\Command\Locator\CommandListenersLocatorInterface;
use Componenta\CQRS\Command\Middleware\EventMiddleware;
use Componenta\CQRS\Command\Middleware\HandleCommandHandler;
use Componenta\CQRS\Query\Factory\HandleQueryFactory;
use Componenta\CQRS\Query\Factory\QueryBusFactory;
use Componenta\CQRS\Query\Factory\QueryHandlerLocatorFactory;
use Componenta\CQRS\Query\HandleQuery;
use Componenta\CQRS\Query\Locator\QueryHandlerLocatorInterface;
use Componenta\CQRS\Query\QueryBusInterface;

class ConfigProvider extends \Componenta\Config\ConfigProvider
{
    protected function getFactories(): array
    {
        return [
            QueryBusInterface::class => QueryBusFactory::class,
            QueryHandlerLocatorInterface::class => QueryHandlerLocatorFactory::class,
            HandleQuery::class => HandleQueryFactory::class,
            CommandBusInterface::class => Command\Factory\CommandBusFactory::class,
            CommandAttributeProviderInterface::class => Command\Factory\CommandAttributeProviderFactory::class,
            CommandHandlerLocator::class => Command\Factory\CommandHandlerLocatorFactory::class,
            HandleCommandHandler::class => Command\Factory\HandleCommandHandlerFactory::class,
            EventMiddleware::class => Command\Factory\EventMiddlewareFactory::class,
            CommandListenersLocator::class => Command\Factory\CommandListenerLocatorFactory::class,
        ];
    }

    protected function getAliases(): array
    {
        return [
            CommandHandlerLocatorInterface::class => CommandHandlerLocator::class,
            CommandListenersLocatorInterface::class => CommandListenersLocator::class,
        ];
    }

    protected function getConfig(): array
    {
        return [
            ConfigKey::COMMAND_MIDDLEWARES => [],
            ConfigKey::QUERY_MIDDLEWARES => [],
        ];
    }
}
