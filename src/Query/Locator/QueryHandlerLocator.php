<?php

declare(strict_types=1);

namespace Componenta\CQRS\Query\Locator;

use Componenta\CQRS\Map\CqrsMapProviderInterface;
use Componenta\CQRS\Query\Exception\HandlerNotFoundException;
use Componenta\CQRS\Query\Exception\InvalidHandlerException;
use Componenta\CQRS\Query\Resolver\QueryNameResolution;
use Componenta\CQRS\Query\Resolver\QueryNameResolverInterface;
use Psr\Container\ContainerInterface;

final class QueryHandlerLocator implements QueryHandlerLocatorInterface, QuerySupportAwareInterface
{
    use QueryNameResolution;

    public function __construct(
        private readonly CqrsMapProviderInterface $mapProvider,
        private readonly ContainerInterface $container,
        ?QueryNameResolverInterface $resolver = null,
    ) {
        $this->resolver = $resolver;
    }

    public function locateFor(object $query): callable
    {
        $queryName = $this->resolveQueryName($query);
        $descriptor = $this->mapProvider->map()->queryHandler($queryName);

        if ($descriptor === null) {
            throw new HandlerNotFoundException($query, queryName: $queryName);
        }

        $handler = $this->container->get($descriptor->service);

        if ($descriptor->method === '__invoke') {
            if (!is_callable($handler)) {
                throw InvalidHandlerException::serviceNotInvokable(
                    $descriptor->service,
                    $queryName,
                );
            }

            return $handler;
        }

        if (!is_callable([$handler, $descriptor->method])) {
            throw InvalidHandlerException::serviceMethodNotCallable(
                $descriptor->service,
                $descriptor->method,
            );
        }

        return $handler->{$descriptor->method}(...);
    }

    public function supports(object $query): bool
    {
        return $this->mapProvider->map()->queryHandler(
            $this->resolveQueryName($query),
        ) !== null;
    }
}
