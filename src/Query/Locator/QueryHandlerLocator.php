<?php

declare(strict_types=1);

namespace Componenta\CQRS\Query\Locator;

use Componenta\CQRS\Map\CqrsMapProviderInterface;
use Componenta\CQRS\Query\Exception\HandlerNotFoundException;
use Componenta\CQRS\Query\Resolver\QueryNameResolution;
use Componenta\CQRS\Query\Resolver\QueryNameResolverInterface;
use LogicException;
use Psr\Container\ContainerInterface;

final class QueryHandlerLocator implements QueryHandlerLocatorInterface, QuerySupportAwareInterface
{
    use QueryNameResolution;

    /** @var array<string, callable> */
    private array $resolvedHandlers = [];

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

        if (isset($this->resolvedHandlers[$queryName])) {
            return $this->resolvedHandlers[$queryName];
        }

        $descriptor = $this->mapProvider->map()->queryHandler($queryName);

        if ($descriptor === null) {
            throw new HandlerNotFoundException($query);
        }

        $handler = $this->container->get($descriptor->service);

        if ($descriptor->method === '__invoke') {
            if (!is_callable($handler)) {
                throw new LogicException(sprintf(
                    'CQRS query handler service "%s" for "%s" is not invokable.',
                    $descriptor->service,
                    $queryName,
                ));
            }

            return $this->resolvedHandlers[$queryName] = $handler;
        }

        if (!is_callable([$handler, $descriptor->method])) {
            throw new LogicException(sprintf(
                'CQRS query handler service "%s" has no public callable method "%s".',
                $descriptor->service,
                $descriptor->method,
            ));
        }

        return $this->resolvedHandlers[$queryName] = $handler->{$descriptor->method}(...);
    }

    public function supports(object $query): bool
    {
        return $this->mapProvider->map()->queryHandler(
            $this->resolveQueryName($query),
        ) !== null;
    }
}
