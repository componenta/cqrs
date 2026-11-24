<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Event;

use Componenta\CQRS\Command\OperationInterface;
use Throwable;
use Psr\Clock\ClockInterface;

final class CommandFailedEvent extends CommandEvent
{
    /**
     * Creates a new command failed event.
     *
     * @param OperationInterface $operation The operation tracking the execution
     * @param Throwable $exception The exception that caused the failure
     */
    public function __construct(
        OperationInterface $operation,
        public readonly Throwable $exception,
        ?ClockInterface $clock = null,
    ) {
        parent::__construct($operation, $clock);
    }
}
