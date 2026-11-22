<?php

declare(strict_types=1);

use Componenta\CQRS\Command\Locator\CommandHandlerLocatorInterface;
use Componenta\CQRS\Command\Middleware\HandleCommandHandler;
use Componenta\CQRS\Command\Operation;
use Componenta\CQRS\Command\OperationResult;
use Componenta\DI\CallableInvokerInterface;

it('rejects a completed built-in operation before locating or executing its handler', function (): void {
    $locator = new class implements CommandHandlerLocatorInterface {
        public int $calls = 0;
        public function locateFor(object $command): callable
        {
            ++$this->calls;
            return static fn (object $command): string => 'performed';
        }
    };
    $invoker = new class implements CallableInvokerInterface {
        public int $calls = 0;
        public function call(mixed $callable, array $params = []): mixed
        {
            ++$this->calls;
            return $callable(...$params);
        }
    };
    $operation = Operation::create(new stdClass())->withResult(new OperationResult(null));

    expect(fn () => (new HandleCommandHandler($locator, $invoker))->handle($operation))
        ->toThrow(RuntimeException::class, 'Operation already has result')
        ->and($locator->calls)->toBe(0)
        ->and($invoker->calls)->toBe(0);
});

it('keeps the built-in operation result single-assignment contract', function (): void {
    $operation = Operation::create(new stdClass())->withResult(new OperationResult('first'));

    expect(fn () => $operation->withResult(new OperationResult('second')))
        ->toThrow(RuntimeException::class, 'Operation already has result');
});
