<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command;

use DateTimeImmutable;
use Ramsey\Uuid\UuidInterface;

/**
 * Immutable command dispatch context.
 *
 * Operation identity and creation time describe one dispatch. Handler completion
 * is represented separately by OperationResult::processedAt.
 */
interface OperationInterface
{
    /** Unique operation identifier used for tracing and idempotency. */
    public UuidInterface $id { get; }

    /** Timestamp when this operation object was created for dispatch. */
    public DateTimeImmutable $createdAt { get; }

    /** Execution result or null when no synchronous result exists. */
    public ?OperationResult $result { get; }

    /** Command associated with this operation. */
    public object $command { get; }

    /** @var array<string, mixed> */
    public array $attributes { get; }

    public function withResult(OperationResult $result): OperationInterface;

    /** @param array<string, mixed> $attributes Complete replacement attributes. */
    public function withAttributes(array $attributes): OperationInterface;

    public function withAttribute(string $name, mixed $value): OperationInterface;

    public function withoutAttribute(string $name): OperationInterface;
}
