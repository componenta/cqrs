<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Result of completed operation.
 */
final readonly class OperationResult
{
    public DateTimeImmutable $processedAt;

    /**
     * @param mixed $value Handler return value
     * @param DateTimeImmutable|null $processedAt Completion timestamp (defaults to now)
     */
    public function __construct(
        public mixed $value,
        ?DateTimeImmutable $processedAt = null,
    ) {
        $this->processedAt = $processedAt ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
