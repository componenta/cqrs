<?php

declare(strict_types=1);

use Componenta\CQRS\Command\Resolver\CommandNameResolverInterface;
use Componenta\CQRS\Command\Resolver\CompositeCommandNameResolver;
use Componenta\CQRS\Map\CqrsMap;
use Componenta\CQRS\Map\CqrsMapProviderInterface;
use Componenta\CQRS\Query\Exception\HandlerNotFoundException;
use Componenta\CQRS\Query\Locator\QueryHandlerLocator;
use Componenta\CQRS\Query\NamedQueryInterface;
use Componenta\CQRS\Query\Resolver\CompositeQueryNameResolver;
use Componenta\CQRS\Query\Resolver\QueryNameResolverInterface;
use Psr\Container\ContainerInterface;

it('reports command support supplied by the composite fallback resolver', function (): void {
    $fallback = new class implements CommandNameResolverInterface {
        public function supports(object $command): bool
        {
            return true;
        }

        public function resolve(object $command): string
        {
            return 'fallback.command';
        }
    };
    $custom = new class implements CommandNameResolverInterface {
        public function supports(object $command): bool
        {
            return false;
        }

        public function resolve(object $command): string
        {
            throw new RuntimeException('Unsupported resolver was called.');
        }
    };
    $resolver = new CompositeCommandNameResolver($fallback, $custom);

    expect($resolver->supports(new stdClass()))->toBeTrue()
        ->and($resolver->resolve(new stdClass()))->toBe('fallback.command');
});

it('reports query support supplied by the composite fallback resolver', function (): void {
    $fallback = new class implements QueryNameResolverInterface {
        public function supports(object $query): bool
        {
            return true;
        }

        public function resolve(object $query): string
        {
            return 'fallback.query';
        }
    };
    $custom = new class implements QueryNameResolverInterface {
        public function supports(object $query): bool
        {
            return false;
        }

        public function resolve(object $query): string
        {
            throw new RuntimeException('Unsupported resolver was called.');
        }
    };
    $resolver = new CompositeQueryNameResolver($fallback, $custom);

    expect($resolver->supports(new stdClass()))->toBeTrue()
        ->and($resolver->resolve(new stdClass()))->toBe('fallback.query');
});

it('reports the resolved logical query name when no handler exists', function (): void {
    $mapProvider = new class implements CqrsMapProviderInterface {
        public function map(): CqrsMap
        {
            return CqrsMap::empty();
        }
    };
    $container = new class implements ContainerInterface {
        public function get(string $id): mixed
        {
            throw new RuntimeException($id);
        }

        public function has(string $id): bool
        {
            return false;
        }
    };
    $query = new readonly class('logical.query') implements NamedQueryInterface {
        public function __construct(public string $queryName)
        {
        }
    };
    $locator = new QueryHandlerLocator($mapProvider, $container);

    try {
        $locator->locateFor($query);
        test()->fail('Expected a missing query handler exception.');
    } catch (HandlerNotFoundException $exception) {
        expect($exception->query)->toBe($query)
            ->and($exception->queryName)->toBe('logical.query')
            ->and($exception->getMessage())->toContain('logical.query');
    }
});
