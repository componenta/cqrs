<?php

declare(strict_types=1);

use Componenta\CQRS\Query\Context\Context;
use Componenta\CQRS\Query\Context\ContextInterface;
use Componenta\CQRS\Query\HandleQuery;
use Componenta\CQRS\Query\Locator\QueryHandlerLocatorInterface;
use Componenta\CQRS\Query\Middleware\MiddlewareInterface;
use Componenta\CQRS\Query\QueryBus;
use Componenta\CQRS\Tests\Fixture\FakeQuery;
use Componenta\DI\CallableInvokerInterface;

function makeHandleQuery(): HandleQuery {
    $locator = new class implements QueryHandlerLocatorInterface {
        public function locateFor(object $query): callable {
            return static fn(FakeQuery $q): string => 'handled:' . $q->tag;
        }
    };
    $invoker = new class implements CallableInvokerInterface {
        public function call(mixed $callable, array $params = []): mixed {
            return $callable(...$params);
        }
    };

    return new HandleQuery($locator, $invoker);
}

it('runs a query without middleware and returns the handler result', function () {
    $bus = new QueryBus(makeHandleQuery());

    expect($bus->handle(new FakeQuery('plain')))->toBe('handled:plain');
});

it('promotes an array context to Context when passing through middleware', function () {
    $middleware = new class implements MiddlewareInterface {
        public ?ContextInterface $received = null;
        public function handle(object $query, ContextInterface $context, callable $next): mixed {
            $this->received = $context;
            return $next($query, $context);
        }
    };

    $bus = new QueryBus(makeHandleQuery(), $middleware);

    $bus->handle(new FakeQuery(), ['key' => 'value']);

    expect($middleware->received)->toBeInstanceOf(ContextInterface::class)
        ->and($middleware->received->getAttribute('key'))->toBe('value');
});

it('passes a ContextInterface instance through middleware unchanged', function () {
    $middleware = new class implements MiddlewareInterface {
        public ?ContextInterface $received = null;
        public function handle(object $query, ContextInterface $context, callable $next): mixed {
            $this->received = $context;
            return $next($query, $context);
        }
    };

    $context = new Context(['pre' => 'set']);
    $bus = new QueryBus(makeHandleQuery(), $middleware);

    $bus->handle(new FakeQuery(), $context);

    expect($middleware->received)->toBe($context);
});

it('chains middlewares in registration order, onion-style', function () {
    // Shared buffer - anonymous classes record their execution.
    $observed = new ArrayObject();

    $makeMw = static function (string $tag) use ($observed): MiddlewareInterface {
        return new readonly class($observed, $tag) implements MiddlewareInterface {
            public function __construct(private ArrayObject $observed, private string $tag) {}

            public function handle(object $query, ContextInterface $context, callable $next): mixed {
                $this->observed[] = "{$this->tag}-before";
                $result = $next($query, $context);
                $this->observed[] = "{$this->tag}-after";
                return $result;
            }
        };
    };

    $bus = new QueryBus(makeHandleQuery(), $makeMw('mw1'), $makeMw('mw2'));

    $bus->handle(new FakeQuery());

    expect($observed->getArrayCopy())->toBe(['mw1-before', 'mw2-before', 'mw2-after', 'mw1-after']);
});

it('propagates exceptions from the terminal query handler', function () {
    $locator = new class implements QueryHandlerLocatorInterface {
        public function locateFor(object $query): callable {
            return static function (): never {
                throw new RuntimeException('query failed');
            };
        }
    };
    $invoker = new class implements CallableInvokerInterface {
        public function call(mixed $callable, array $params = []): mixed {
            return $callable(...$params);
        }
    };
    $bus = new QueryBus(new HandleQuery($locator, $invoker));

    expect(fn () => $bus->handle(new FakeQuery('boom')))
        ->toThrow(RuntimeException::class, 'query failed');
});
