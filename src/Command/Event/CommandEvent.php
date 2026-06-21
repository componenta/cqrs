<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Event;

use Componenta\CQRS\Command\OperationInterface;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Base class for all command lifecycle events dispatched by {@see EventMiddleware}.
 *
 * Carries the operation context through the event pipeline and provides
 * propagation control - a listener can call {@see self::stop()} to prevent
 * subsequent listeners from being invoked.
 *
 * @internal Not part of the public API; extend only within this package.
 */
class CommandEvent
{
    /**
     * Whether propagation has been stopped by a listener.
     *
     * Computed from `$propagationStoppedAt` - true as soon as any listener
     * calls {@see self::stop()}. EventMiddleware checks this after each
     * listener invocation and skips the rest when true.
     */
    public bool $isPropagationStopped {
        get => $this->propagationStoppedAt !== null;
    }

    /**
     * UTC timestamp of when this event was instantiated.
     *
     * Useful for measuring listener latency or auditing dispatch timing.
     */
    public readonly DateTimeImmutable $dispatchedAt;

    /**
     * UTC timestamp of when propagation was stopped, or null if it hasn't been.
     *
     * Kept separate from the boolean flag so callers can determine
     * *when* propagation was stopped, not just *whether* it was.
     */
    private(set) ?DateTimeImmutable $propagationStoppedAt = null;

    /**
     * @param OperationInterface $operation The operation being executed,
     *        carrying the command, result (if available), and attributes.
     */
    public function __construct(
        public readonly OperationInterface $operation,
    ) {
        $this->dispatchedAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    /**
     * Stops propagation to subsequent listeners.
     *
     * Idempotent - calling this multiple times records only the first
     * timestamp and has no further effect.
     */
    public function stop(): void
    {
        if ($this->propagationStoppedAt === null) {
            $this->propagationStoppedAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        }
    }
}