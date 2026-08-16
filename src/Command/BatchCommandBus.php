<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command;

use InvalidArgumentException;

/**
 * Command bus decorator that can dispatch multiple commands in sequence.
 */
final readonly class BatchCommandBus implements CommandBusInterface
{
    public function __construct(
        private CommandBusInterface $bus,
    ) {
    }

    public function dispatch(object $command, array $attributes = []): OperationInterface
    {
        return $this->bus->dispatch($command, $attributes);
    }

    /**
     * Dispatches commands sequentially with attributes resolved by command class.
     *
     * @param iterable<object> $commands
     * @param array<class-string, array<string, mixed>> $attributes
     * @return list<OperationInterface>
     */
    public function dispatchMany(iterable $commands, array $attributes = []): array
    {
        $batch = [];

        foreach ($commands as $command) {
            if (!is_object($command)) {
                throw new InvalidArgumentException(sprintf(
                    'Batch commands must be objects; got %s.',
                    get_debug_type($command),
                ));
            }

            $commandClass = $command::class;
            $commandAttributes = $attributes[$commandClass] ?? [];

            if (!is_array($commandAttributes)) {
                throw new InvalidArgumentException(sprintf(
                    'Attributes for command "%s" must be an array; got %s.',
                    $commandClass,
                    get_debug_type($commandAttributes),
                ));
            }

            $batch[] = [$command, $commandAttributes];
        }

        $operations = [];

        foreach ($batch as [$command, $commandAttributes]) {
            $operations[] = $this->bus->dispatch($command, $commandAttributes);
        }

        return $operations;
    }
}
