<?php

declare(strict_types=1);

use Componenta\CQRS\Command\Locator\CommandHandlerLocatorInterface;
use Componenta\CQRS\Command\Middleware\HandleCommandHandler;
use Componenta\CQRS\Command\Operation;
use Componenta\CQRS\Command\OperationResult;
use Componenta\CQRS\Tests\Fixture\DecoratedOperation;
use Componenta\DI\CallableInvokerInterface;

it('rejects any completed operation before locating or executing its handler', function (bool $decorated, mixed $value): void {
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
    $operation = Operation::create(new stdClass())->withResult(new OperationResult($value));
    if ($decorated) {
        $operation = new DecoratedOperation($operation);
    }

    expect(fn () => (new HandleCommandHandler($locator, $invoker))->handle($operation))
        ->toThrow(RuntimeException::class, 'Operation already has result')
        ->and($locator->calls)->toBe(0)
        ->and($invoker->calls)->toBe(0);
})->with([
    'built-in operation, null result value' => [false, null],
    'built-in operation, non-null result value' => [false, 'done'],
    'decorated operation, null result value' => [true, null],
    'decorated operation, non-null result value' => [true, 'done'],
]);

it('keeps the built-in operation result single-assignment contract', function (): void {
    $operation = Operation::create(new stdClass())->withResult(new OperationResult('first'));

    expect(fn () => $operation->withResult(new OperationResult('second')))
        ->toThrow(RuntimeException::class, 'Operation already has result');
});

it('processes an unfinished operation supplied through an alternative implementation', function (): void {
    $command = new stdClass();
    $operation = new DecoratedOperation(Operation::create($command, ['trace' => 'original']));
    $locator = new class implements CommandHandlerLocatorInterface {
        public function locateFor(object $command): callable
        {
            return static fn (object $message): object => $message;
        }
    };
    $completed = (new HandleCommandHandler($locator))->handle($operation);

    expect($completed->result?->value)->toBe($command)
        ->and($completed->id)->toBe($operation->id)
        ->and($completed->createdAt)->toBe($operation->createdAt)
        ->and($completed->command)->toBe($command)
        ->and($completed->attributes)->toBe(['trace' => 'original'])
        ->and($operation->result)->toBeNull();
});
