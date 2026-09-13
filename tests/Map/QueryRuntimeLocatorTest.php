<?php

declare(strict_types=1);

use Componenta\CQRS\Query\Exception\InvalidHandlerException;
use Componenta\CQRS\Query\Locator\QueryHandlerLocator;
use Psr\Container\ContainerInterface;

final readonly class QueryRuntimeLocatorTestQuery
{
}

final class QueryRuntimeLocatorTestHandler
{
    public function handle(QueryRuntimeLocatorTestQuery $query): object
    {
        return $query;
    }
}

final class QueryRuntimeLocatorContainer implements ContainerInterface
{
    /** @var array<string, int> */
    public array $gets = [];

    /** @param array<string, mixed> $entries */
    public function __construct(private readonly array $entries)
    {
    }

    public function get(string $id): mixed
    {
        $this->gets[$id] = ($this->gets[$id] ?? 0) + 1;

        return $this->entries[$id] ?? throw new RuntimeException($id);
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->entries);
    }
}

it('resolves a query handler through the container for every locate', function (): void {
    $map = [
        QueryRuntimeLocatorTestQuery::class => ['service' => QueryRuntimeLocatorTestHandler::class, 'method' => 'handle'],
    ];
    $container = new QueryRuntimeLocatorContainer([
        QueryRuntimeLocatorTestHandler::class => new QueryRuntimeLocatorTestHandler(),
    ]);
    $locator = new QueryHandlerLocator(
        $map,
        $container,
    );
    $query = new QueryRuntimeLocatorTestQuery();

    $first = $locator->locateFor($query);
    $second = $locator->locateFor($query);

    expect($first($query))->toBe($query)
        ->and($second($query))->toBe($query)
        ->and($container->gets)->toBe([QueryRuntimeLocatorTestHandler::class => 2]);
});

it('reports invalid mapped query handler services with a typed locator exception', function (): void {
    foreach ([
        ['__invoke', 'is not invokable'],
        ['missingMethod', 'has no public callable method'],
    ] as [$method, $message]) {
        $map = [
            QueryRuntimeLocatorTestQuery::class => ['service' => 'invalid.query.handler', 'method' => $method],
        ];
        $locator = new QueryHandlerLocator(
            $map,
            new QueryRuntimeLocatorContainer([
                'invalid.query.handler' => new stdClass(),
            ]),
        );

        expect(fn () => $locator->locateFor(new QueryRuntimeLocatorTestQuery()))
            ->toThrow(InvalidHandlerException::class, $message);
    }
});
