<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command;

use InvalidArgumentException;
use Ramsey\Uuid\UuidInterface;

/**
 * Command bus decorator that can dispatch multiple command classes in sequence.
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
     * Each command class may occur only once in a batch because both attributes
     * and returned operation IDs are keyed by command class.
     *
     * @param iterable<object> $commands
     * @param array<class-string, array<string, mixed>> $attributes
     * @return array<class-string, UuidInterface>
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

            if (array_key_exists($commandClass, $batch)) {
                throw new InvalidArgumentException(sprintf(
                    'Command class "%s" may occur only once in a batch.',
                    $commandClass,
                ));
            }

            $commandAttributes = $attributes[$commandClass] ?? [];

            if (!is_array($commandAttributes)) {
                throw new InvalidArgumentException(sprintf(
                    'Attributes for command "%s" must be an array; got %s.',
                    $commandClass,
                    get_debug_type($commandAttributes),
                ));
            }

            $batch[$commandClass] = [$command, $commandAttributes];
        }

        $operationIds = [];

        foreach ($batch as $commandClass => [$command, $commandAttributes]) {
            $operationIds[$commandClass] = $this->bus->dispatch($command, $commandAttributes)->id;
        }

        return $operationIds;
    }
}
