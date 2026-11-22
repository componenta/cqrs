<?php

declare(strict_types=1);

use Componenta\CQRS\Command\BatchCommandBus;
use Componenta\CQRS\Command\CommandBusInterface;
use Componenta\CQRS\Command\Operation;
use Componenta\CQRS\Command\OperationInterface;

it('uses empty attributes when a command class is absent from a partial batch map', function (): void {
    $inner = new class implements CommandBusInterface {
        public array $calls = [];
        public function dispatch(object $command, array $attributes = []): OperationInterface
        {
            $this->calls[] = $attributes;
            return Operation::create($command, $attributes);
        }
    };
    $first = new stdClass();
    $second = new DateTimeImmutable();
    $ops = (new BatchCommandBus($inner))->dispatchMany(
        [$first, $second],
        [stdClass::class => ['trace' => 'one']],
    );

    expect($inner->calls)->toBe([['trace' => 'one'], []])
        ->and($ops)->toHaveCount(2);
});

it('rejects explicit null batch attributes before dispatching any command', function (): void {
    $inner = new class implements CommandBusInterface {
        public int $calls = 0;
        public function dispatch(object $command, array $attributes = []): OperationInterface
        {
            ++$this->calls;
            return Operation::create($command, $attributes);
        }
    };
    $bus = new BatchCommandBus($inner);

    expect(fn () => $bus->dispatchMany(
        [new stdClass(), new DateTimeImmutable()],
        [DateTimeImmutable::class => null],
    ))->toThrow(InvalidArgumentException::class, 'must be an array')
        ->and($inner->calls)->toBe(0);
});
