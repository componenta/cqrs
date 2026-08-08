<?php

declare(strict_types=1);

namespace Componenta\CQRS\Query\Factory;

use Componenta\CQRS\Map\CqrsMapProviderInterface;
use Componenta\CQRS\Query\Locator\QueryHandlerLocator;
use Componenta\CQRS\Query\Locator\QueryHandlerLocatorInterface;
use Componenta\CQRS\Query\Resolver\QueryNameResolverInterface;
use LogicException;
use Psr\Container\ContainerInterface;

final class QueryHandlerLocatorFactory
{
    public function __invoke(ContainerInterface $container): QueryHandlerLocatorInterface
    {
        $mapProvider = $container->get(CqrsMapProviderInterface::class);

        if (!$mapProvider instanceof CqrsMapProviderInterface) {
            throw new LogicException(sprintf(
                'Container entry "%s" must implement %s.',
                CqrsMapProviderInterface::class,
                CqrsMapProviderInterface::class,
            ));
        }

        $resolver = null;

        if ($container->has(QueryNameResolverInterface::class)) {
            $resolver = $container->get(QueryNameResolverInterface::class);

            if (!$resolver instanceof QueryNameResolverInterface) {
                throw new LogicException(sprintf(
                    'Container entry "%s" must implement %s.',
                    QueryNameResolverInterface::class,
                    QueryNameResolverInterface::class,
                ));
            }
        }

        return new QueryHandlerLocator(
            $mapProvider,
            $container,
            $resolver,
        );
    }
}
