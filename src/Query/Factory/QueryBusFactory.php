<?php

namespace Componenta\CQRS\Query\Factory;

use Componenta\Config\Config;
use Componenta\CQRS\ConfigKey;
use Componenta\CQRS\Query\HandleQuery;
use Componenta\CQRS\Query\Middleware\MiddlewareInterface;
use Componenta\CQRS\Query\QueryBus;
use Psr\Container\ContainerInterface;

final class QueryBusFactory
{
    public function __invoke(ContainerInterface $container): QueryBus
    {
        /**
         * @var Config $config
         * @var string[] $middlewares
         */
        $config = $container->get(ConfigKey::CONFIG);
        $middlewares = $config->get(ConfigKey::QUERY_MIDDLEWARES, []);

        if ($middlewares === []) {
            return new QueryBus($container->get(HandleQuery::class));
        }

        return new QueryBus(
            $container->get(HandleQuery::class),
            ...array_map(static fn($entryId): MiddlewareInterface => $container->get($entryId), $middlewares)
        );
    }
}