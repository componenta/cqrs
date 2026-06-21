<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command;

use DateTimeImmutable;
use Ramsey\Uuid\UuidInterface;

/**
 * Container for command execution context.
 *
 * Operation carries identification, timestamps, attributes and result
 * through the execution pipeline.
 *
 * ## Responsibilities
 *
 * - **Identification** - unique ID for tracing and log correlation
 * - **Timestamps** - when execution started and completed
 * - **Context** - arbitrary attributes
 * - **Result** - value returned from handler
 *
 * ## Usage
 *
 * ```php
 * $operation = $bus->dispatch(new CreateUserCommand($email));
 *
 * if ($operation->result === null) {
 *     return new JsonResponse([
 *         'operation_id' => $operation->id->toString(),
 *     ], 202);
 * }
 *
 * return new JsonResponse([
 *     'id' => $operation->result->value,
 * ], 201);
 * ```
 *
 * ## Immutability
 *
 * Operation is immutable. All `with*` methods return a new instance:
 *
 * ```php
 * $op1 = Operation::create($command);
 * $op2 = $op1->withAttribute('user_id', 123);
 *
 * // $op1->attributes === []
 * // $op2->attributes === ['user_id' => 123]
 * ```
 */
interface OperationInterface
{
    /**
     * Unique operation identifier.
     *
     * UUID v7 with embedded timestamp - suitable for sorting
     * and distributed tracing.
     *
     * Used for:
     * - Log correlation
     * - Distributed tracing (OpenTelemetry)
     * - HTTP response header `X-Operation-ID`
     */
    public UuidInterface $id { get; }

    /**
     * Timestamp when operation was created.
     *
     * Set once, never changes.
     *
     * Used for:
     * - Duration calculation
     * - Metrics and monitoring
     * - Audit logs
     */
    public DateTimeImmutable $startedAt { get; }

    /**
     * Execution result or null if async.
     *
     * Contains:
     * - `value: mixed` - handler return value
     * - `processedAt: DateTimeImmutable` - completion timestamp
     */
    public ?OperationResult $result { get; }

    /**
     * Command being executed.
     */
    public object $command { get; }

    /**
     * Arbitrary attributes.
     *
     * @return array<string, mixed>
     */
    public array $attributes { get; }

    /**
     * Returns new instance with result.
     *
     * @param OperationResult $result Execution result
     * @return OperationInterface New instance with result
     */
    public function withResult(OperationResult $result): OperationInterface;

    /**
     * Returns new instance with replaced attributes.
     *
     * @param array<string, mixed> $attributes New attributes
     * @return OperationInterface New instance with attributes
     */
    public function withAttributes(array $attributes): OperationInterface;

    /**
     * Returns new instance with added/changed attribute.
     *
     * @param string $name Attribute name
     * @param mixed $value Attribute value
     * @return OperationInterface New instance with attribute
     */
    public function withAttribute(string $name, mixed $value): OperationInterface;

    /**
     * Returns new instance without specified attribute.
     *
     * @param string $name Attribute name to remove
     * @return OperationInterface New instance without attribute
     */
    public function withoutAttribute(string $name): OperationInterface;
}




