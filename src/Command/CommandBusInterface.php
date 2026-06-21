<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command;

/**
 * Dispatches commands for execution.
 */
interface CommandBusInterface
{
    /**
     * Dispatches a command for execution.
     *
     * @param object $command The command to dispatch
     * @param array<string, mixed> $attributes Context attributes for the operation
     * @return OperationInterface The operation with execution result
     */
    public function dispatch(object $command, array $attributes = []): OperationInterface;
}