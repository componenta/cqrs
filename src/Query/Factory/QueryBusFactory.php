<?php

declare(strict_types=1);

namespace Componenta\CQRS\Query\Factory;

use Componenta\Config\Config;
use Componenta\CQRS\ConfigKey;
use Componenta\CQRS\Query\HandleQuery;
use Componenta\CQRS\Query\Middleware\MiddlewareInterface;
use Componenta\CQRS\Query\QueryBus;
use LogicException;
use Psr\Container\ContainerInterface;

final class QueryBusFactory
{
    public function __invoke(ContainerInterface $container): QueryBus
    {
        $config = $container->get(ConfigKey::CONFIG);

        if (!$config instanceof Config) {
            throw new LogicException(sprintf(
                'Container entry "%s" must be a %s instance.',
                ConfigKey::CONFIG,
                Config::class,
            ));
        }

        $handler = $container->get(HandleQuery::class);

        if (!$handler instanceof HandleQuery) {
            throw new LogicException(sprintf(
                'Container entry "%s" must be a %s instance.',
                HandleQuery::class,
                HandleQuery::class,
            ));
        }

        $middlewareIds = $config->get(ConfigKey::QUERY_MIDDLEWARES, []);

        if (!is_array($middlewareIds) || !array_is_list($middlewareIds)) {
            throw new LogicException(sprintf(
                'Configuration entry "%s" must be a list of middleware service ids.',
                ConfigKey::QUERY_MIDDLEWARES,
            ));
        }

        $middlewares = [];

        foreach ($middlewareIds as $index => $middlewareId) {
            if (!is_string($middlewareId) || trim($middlewareId) === '') {
                throw new LogicException(sprintf(
                    'Query middleware service id at index %d must be a non-empty string.',
                    $index,
                ));
            }

            $middleware = $container->get($middlewareId);

            if (!$middleware instanceof MiddlewareInterface) {
                throw new LogicException(sprintf(
                    'Query middleware service "%s" must implement %s; got %s.',
                    $middlewareId,
                    MiddlewareInterface::class,
                    get_debug_type($middleware),
                ));
            }

            $middlewares[] = $middleware;
        }

        return new QueryBus($handler, ...$middlewares);
    }
}
