<?php

namespace Componenta\CQRS\Query\Factory;

use Componenta\Config\Config;
use Componenta\CQRS\ConfigKey;
use Componenta\CQRS\Query\Locator\QueryHandlerLocator;
use Componenta\CQRS\Query\Resolver\QueryNameResolverInterface;
use Psr\Container\ContainerInterface;

final class QueryHandlerLocatorFactory
{
    public function __invoke(ContainerInterface $container): QueryHandlerLocator
    {
        /**
         * @var Config $config
         */
        $config = $container->get(ConfigKey::CONFIG);
        return new QueryHandlerLocator(
            $config->get(ConfigKey::QUERY_HANDLER_MAP, []),
            $container->has(QueryNameResolverInterface::class) ?
                $container->get(QueryNameResolverInterface::class) : null,
            $container,
        );
    }
}
