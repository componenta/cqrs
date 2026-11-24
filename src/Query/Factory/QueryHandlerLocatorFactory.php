<?php

declare(strict_types=1);

namespace Componenta\CQRS\Query\Factory;

use Componenta\CQRS\ConfigKey;
use Componenta\CQRS\Query\Locator\QueryHandlerLocator;
use Componenta\CQRS\Query\Locator\QueryHandlerLocatorInterface;
use Componenta\CQRS\Query\Resolver\QueryNameResolverInterface;
use Componenta\CQRS\Exception\InvalidConfigurationException;
use Psr\Container\ContainerInterface;

final class QueryHandlerLocatorFactory
{
    public function __invoke(ContainerInterface $container): QueryHandlerLocatorInterface
    {
        $maps = $container->get(ConfigKey::MAPS);

        if (!is_array($maps) || !is_array($maps['query_handlers'] ?? null)) {
            throw new InvalidConfigurationException(sprintf(
                'Container entry "%s" must contain array "query_handlers".',
                ConfigKey::MAPS,
            ));
        }

        $resolver = null;

        if ($container->has(QueryNameResolverInterface::class)) {
            $resolver = $container->get(QueryNameResolverInterface::class);

            if (!$resolver instanceof QueryNameResolverInterface) {
                throw new InvalidConfigurationException(sprintf(
                    'Container entry "%s" must implement %s.',
                    QueryNameResolverInterface::class,
                    QueryNameResolverInterface::class,
                ));
            }
        }

        return new QueryHandlerLocator(
            $maps['query_handlers'],
            $container,
            $resolver,
        );
    }
}
