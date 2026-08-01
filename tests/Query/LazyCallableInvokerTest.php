<?php

declare(strict_types=1);

use Componenta\CQRS\Handler\DirectHandlerCallableInterface;
use Componenta\CQRS\Query\HandleQuery;
use Componenta\CQRS\Query\LazyCallableInvoker;
use Componenta\CQRS\Query\Locator\QueryHandlerLocatorInterface;
use Componenta\DI\CallableInvoker;

it('does not create the generic invoker for direct handlers', function () {
    $factoryCalls = 0;
    $handler = new class implements DirectHandlerCallableInterface {
        public function __invoke(object $message): mixed
        {
            return 'direct';
        }
    };
    $locator = new class($handler) implements QueryHandlerLocatorInterface {
        public function __construct(private readonly DirectHandlerCallableInterface $handler) {}

        public function locateFor(object $query): callable
        {
            return $this->handler;
        }
    };
    $invoker = new LazyCallableInvoker(static function () use (&$factoryCalls): CallableInvoker {
        ++$factoryCalls;
        return new CallableInvoker();
    });

    expect((new HandleQuery($locator, $invoker))(new stdClass()))->toBe('direct')
        ->and($factoryCalls)->toBe(0);
});

it('creates and reuses the generic invoker for legacy handlers', function () {
    $factoryCalls = 0;
    $locator = new class implements QueryHandlerLocatorInterface {
        public function locateFor(object $query): callable
        {
            return static fn(object $message): string => 'legacy';
        }
    };
    $invoker = new LazyCallableInvoker(static function () use (&$factoryCalls): CallableInvoker {
        ++$factoryCalls;
        return new CallableInvoker();
    });
    $handle = new HandleQuery($locator, $invoker);

    expect($handle(new stdClass()))->toBe('legacy')
        ->and($handle(new stdClass()))->toBe('legacy')
        ->and($factoryCalls)->toBe(1);
});
