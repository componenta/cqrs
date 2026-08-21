<?php

declare(strict_types=1);

use Componenta\CQRS\Map\CqrsMap;
use Componenta\CQRS\Map\CqrsMapProviderInterface;
use Componenta\CQRS\Map\HandlerDescriptor;
use Componenta\CQRS\Query\Exception\InvalidHandlerException;
use Componenta\CQRS\Query\Locator\QueryHandlerLocator;
use Psr\Container\ContainerInterface;

final readonly class QueryRuntimeLocatorTestQuery
{
}

final readonly class QueryRuntimeLocatorMapProvider implements CqrsMapProviderInterface
{
    public function __construct(private CqrsMap $map) {}

    public function map(): CqrsMap
    {
        return $this->map;
    }
}

final readonly class QueryRuntimeLocatorContainer implements ContainerInterface
{
    /** @param array<string, mixed> $entries */
    public function __construct(private array $entries) {}

    public function get(string $id): mixed
    {
        return $this->entries[$id] ?? throw new RuntimeException($id);
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->entries);
    }
}

it('reports invalid mapped query handler services with a typed locator exception', function (): void {
    foreach ([
        ['__invoke', 'is not invokable'],
        ['missingMethod', 'has no public callable method'],
    ] as [$method, $message]) {
        $map = new CqrsMap(queryHandlers: [
            QueryRuntimeLocatorTestQuery::class => new HandlerDescriptor(
                'invalid.query.handler',
                $method,
            ),
        ]);
        $locator = new QueryHandlerLocator(
            new QueryRuntimeLocatorMapProvider($map),
            new QueryRuntimeLocatorContainer([
                'invalid.query.handler' => new stdClass(),
            ]),
        );

        expect(fn() => $locator->locateFor(new QueryRuntimeLocatorTestQuery()))
            ->toThrow(InvalidHandlerException::class, $message);
    }
});
