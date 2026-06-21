<?php

namespace Componenta\CQRS\Query\Factory;

use Componenta\CQRS\Query\HandleQuery;
use Componenta\CQRS\Query\Locator\QueryHandlerLocatorInterface;
use Componenta\DI\CallableInvoker;
use Componenta\DI\CallableInvokerInterface;
use Psr\Container\ContainerInterface;

final class HandleQueryFactory
{
    public function __invoke(ContainerInterface $container): HandleQuery
    {
        return new HandleQuery(
            $container->get(QueryHandlerLocatorInterface::class),
            $container->has(CallableInvokerInterface::class) ?
                $container->get(CallableInvokerInterface::class) : new CallableInvoker(),
        );
    }
}