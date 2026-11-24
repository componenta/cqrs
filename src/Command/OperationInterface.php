<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command;

use Componenta\CQRS\Exception\ExceptionInterface;
use DateTimeImmutable;
use Ramsey\Uuid\UuidInterface;

/**
 * Immutable command dispatch context.
 *
 * Each with/without method returns a new operation without changing the original.
 * Derived operations preserve the identifier, creation time, and command instance.
 * Attribute changes preserve the result. Result changes preserve the attributes.
 * Immutability is shallow: command and attribute objects are not deep-cloned.
 */
interface OperationInterface
{
    /** Unique identifier of the operation, preserved in derived operations. */
    public UuidInterface $id { get; }

    /** Local operation creation time, not handler start time. The default factory uses UTC. */
    public DateTimeImmutable $createdAt { get; }

    /**
     * Execution result or null when no synchronous result exists.
     * A non-null result marks a completed operation, even when its value is null.
     */
    public ?OperationResult $result { get; }

    /** Command associated with this operation. */
    public object $command { get; }

    /**
     * Attribute names are non-empty strings, excluding whitespace-only names
     * and strings that PHP converts to integer array keys.
     *
     * @var array<string, mixed>
     */
    public array $attributes { get; }

    /**
     * Returns a new operation with the result set.
     *
     * @throws ExceptionInterface If this operation already has a result.
     */
    public function withResult(OperationResult $result): OperationInterface;

    /**
     * Returns a new operation with all attributes replaced.
     *
     * @param array<string, mixed> $attributes
     * @throws ExceptionInterface If any attribute name is invalid.
     */
    public function withAttributes(array $attributes): OperationInterface;

    /**
     * Returns a new operation with one attribute added or replaced.
     *
     * @throws ExceptionInterface If the attribute name is invalid.
     */
    public function withAttribute(string $name, mixed $value): OperationInterface;

    /**
     * Returns a new operation without the named attribute.
     * A missing valid name leaves the attribute values unchanged.
     *
     * @throws ExceptionInterface If the attribute name is invalid.
     */
    public function withoutAttribute(string $name): OperationInterface;
}
