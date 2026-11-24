<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Event;

use Componenta\CQRS\Command\OperationInterface;
use DateTimeImmutable;
use DateTimeZone;
use Psr\Clock\ClockInterface;

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
     * Timestamp of when this event was instantiated (UTC without a clock).
     *
     * Useful for auditing dispatch timing. Wall-clock values are not monotonic.
     */
    public readonly DateTimeImmutable $dispatchedAt;

    /**
     * Timestamp of the first successful stop, or null if propagation has not stopped.
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
        private readonly ?ClockInterface $clock = null,
    ) {
        $this->dispatchedAt = $this->now();
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
            $this->propagationStoppedAt = $this->now();
        }
    }

    private function now(): DateTimeImmutable
    {
        return $this->clock?->now() ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}