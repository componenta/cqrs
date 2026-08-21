<?php

declare(strict_types=1);

use Componenta\CQRS\Command\BatchCommandBus;
use Componenta\CQRS\Command\CommandBusInterface;
use Componenta\CQRS\Command\Operation;
use Componenta\CQRS\Command\OperationInterface;

final readonly class BatchCommandBusFirstCommand
{
    public function __construct(public string $value) {}
}

final readonly class BatchCommandBusSecondCommand
{
    public function __construct(public string $value) {}
}

final readonly class BatchCommandBusDuplicateCommand
{
    public function __construct(public string $value) {}
}

final class RecordingBatchCommandBus implements CommandBusInterface
{
    /** @var list<OperationInterface> */
    public array $operations = [];

    public function dispatch(object $command, array $attributes = []): OperationInterface
    {
        $operation = Operation::create($command, $attributes);
        $this->operations[] = $operation;

        return $operation;
    }
}

it('proxies single command dispatch unchanged', function () {
    $inner = new RecordingBatchCommandBus();
    $bus = new BatchCommandBus($inner);
    $command = new BatchCommandBusFirstCommand('one');

    $operation = $bus->dispatch($command, ['trace_id' => 'trace-1']);

    expect($operation->command)->toBe($command)
        ->and($operation->attributes)->toBe(['trace_id' => 'trace-1'])
        ->and($inner->operations)->toBe([$operation]);
});

it('dispatches many commands and returns operations as a list', function () {
    $inner = new RecordingBatchCommandBus();
    $bus = new BatchCommandBus($inner);
    $first = new BatchCommandBusFirstCommand('one');
    $second = new BatchCommandBusSecondCommand('two');

    $operations = $bus->dispatchMany(
        (static function () use ($first, $second): Generator {
            yield $first;
            yield $second;
        })(),
        [
            BatchCommandBusFirstCommand::class => ['trace_id' => 'trace-1'],
            BatchCommandBusSecondCommand::class => ['trace_id' => 'trace-2'],
        ],
    );

    expect($inner->operations)->toHaveCount(2)
        ->and($inner->operations[0]->command)->toBe($first)
        ->and($inner->operations[0]->attributes)->toBe(['trace_id' => 'trace-1'])
        ->and($inner->operations[1]->command)->toBe($second)
        ->and($inner->operations[1]->attributes)->toBe(['trace_id' => 'trace-2'])
        ->and($operations)->toBe([
            $inner->operations[0],
            $inner->operations[1],
        ]);
});

it('allows duplicate command classes and applies class attributes to each command', function () {
    $inner = new RecordingBatchCommandBus();
    $bus = new BatchCommandBus($inner);
    $first = new BatchCommandBusDuplicateCommand('one');
    $second = new BatchCommandBusDuplicateCommand('two');

    $operations = $bus->dispatchMany(
        [$first, $second],
        [BatchCommandBusDuplicateCommand::class => ['trace_id' => 'trace-duplicate']],
    );

    expect($inner->operations)->toHaveCount(2)
        ->and($inner->operations[0]->command)->toBe($first)
        ->and($inner->operations[1]->command)->toBe($second)
        ->and($inner->operations[0]->attributes)->toBe(['trace_id' => 'trace-duplicate'])
        ->and($inner->operations[1]->attributes)->toBe(['trace_id' => 'trace-duplicate'])
        ->and($operations)->toBe([
            $inner->operations[0],
            $inner->operations[1],
        ]);
});

it('rejects malformed selected attributes before dispatching any command', function (array $invalidAttributes): void {
    $inner = new RecordingBatchCommandBus();
    $bus = new BatchCommandBus($inner);

    expect(fn() => $bus->dispatchMany(
        [
            new BatchCommandBusFirstCommand('one'),
            new BatchCommandBusSecondCommand('two'),
        ],
        [
            BatchCommandBusFirstCommand::class => ['trace_id' => 'trace-1'],
            BatchCommandBusSecondCommand::class => $invalidAttributes,
        ],
    ))->toThrow(InvalidArgumentException::class, 'non-empty string names')
        ->and($inner->operations)->toBe([]);
})->with([
    'integer key' => [[0 => 'value']],
    'empty string' => [['' => 'value']],
    'whitespace string' => [['   ' => 'value']],
]);
