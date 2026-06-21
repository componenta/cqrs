<?php

namespace Componenta\CQRS\Query\Locator;

use Componenta\CQRS\Query\Exception\HandlerNotFoundException;
use Componenta\CQRS\Query\Resolver\QueryNameResolution;
use Componenta\CQRS\Query\Resolver\QueryNameResolverInterface;
use Psr\Container\ContainerInterface;

/**
 * Plain query-handler locator backed by a flat map.
 *
 * Map entries may be:
 *  - a `callable` (Closure, invokable instance, `[$instance, $method]`),
 *    used as-is and returned directly.
 *  - a `[class-string, method-string]` pair - the form produced by
 *    {@see \Componenta\CQRS\App\Query\Locator\AttributeQueryHandlerLocator::toArray()} and serialised into
 *    `config.cache.php` for production. Resolution is lazy: the handler
 *    instance is fetched from the container the first time the query is
 *    located, then bound to the method via first-class-callable syntax
 *    (or returned as-is when `$method === '__invoke'`).
 *
 * The container is optional only to keep test fixtures terse - a map
 * containing class/method pairs without a container raises a clear
 * exception at lookup time.
 */
final class QueryHandlerLocator implements QueryHandlerLocatorInterface, QuerySupportAwareInterface
{
    use QueryNameResolution;

    /** @var array<string, callable|array{0: class-string, 1: string}> */
    private array $map;

    public function __construct(
        array $map = [],
        ?QueryNameResolverInterface $resolver = null,
        private readonly ?ContainerInterface $container = null,
    ) {
        $this->map = $map;
        $this->resolver = $resolver;
    }

    public function register(string $queryName, callable $handler): void
    {
        $this->map[$queryName] = $handler;
    }

    public function locateFor(object $query): callable
    {
        $queryName = $this->resolveQueryName($query);

        if (!isset($this->map[$queryName])) {
            throw new HandlerNotFoundException($query);
        }

        $entry = $this->map[$queryName];

        if (is_callable($entry)) {
            return $entry;
        }

        // Class/method pair from a compiled cache - resolve lazily.
        if (is_array($entry) && isset($entry[0], $entry[1]) && is_string($entry[0]) && is_string($entry[1])) {
            if ($this->container === null) {
                throw new \LogicException(sprintf(
                    'QueryHandlerLocator: cannot resolve handler "%s::%s" for "%s" - '
                    . 'no container was supplied. Pass one in the constructor when '
                    . 'using compiled `[class, method]` pairs.',
                    $entry[0], $entry[1], $queryName,
                ));
            }

            $handler = $this->container->get($entry[0]);
            $callable = $entry[1] === '__invoke' ? $handler : $handler->{$entry[1]}(...);
            $this->map[$queryName] = $callable; // memoise

            return $callable;
        }

        throw new \LogicException(sprintf(
            'QueryHandlerLocator: handler entry for "%s" must be callable or '
            . '[class-string, method-string]; got %s.',
            $queryName,
            get_debug_type($entry),
        ));
    }

    public function supports(object $query): bool
    {
        return isset($this->map[$this->resolveQueryName($query)]);
    }
}
