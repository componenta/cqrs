<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command;

use Componenta\CQRS\Clock\UtcClock;
use DateTimeImmutable;

/**
 * Result of completed operation.
 */
final readonly class OperationResult
{
    public DateTimeImmutable $processedAt;

    /**
     * @param mixed $value Handler return value
     * @param DateTimeImmutable|null $processedAt Completion timestamp (defaults to current UTC time)
     */
    public function __construct(
        public mixed $value,
        ?DateTimeImmutable $processedAt = null,
    ) {
        $this->processedAt = $processedAt ?? (new UtcClock())->now();
    }
}
