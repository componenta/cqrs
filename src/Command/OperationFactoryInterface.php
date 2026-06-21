<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command;

/**
 * Factory interface for creating operation instances.
 *
 * This contract is useful when code creates operations explicitly. The current
 * CommandBus creates operations directly through Operation::create().
 *
 * @example
 * ```php
 * // Custom factory with tracing
 * class TracedOperationFactory implements OperationFactoryInterface
 * {
 *     public function __construct(
 *         private string $serviceName,
 *     ) {}
 *
 *     public function create(object $command, array $attributes = []): OperationInterface
 *     {
 *         $operation = Operation::create($command, $attributes);
 *         $_SERVER['HTTP_X_TRACE_ID'] = (string)$operation->uuid;
 *         return $operation;
 *     }
 * }
 * ```
 *
 * @example
 * ```php
 * // Testing factory with predictable UUIDs
 * class TestOperationFactory implements OperationFactoryInterface
 * {
 *     private int $counter = 0;
 *
 *     public function create(object $command, array $attributes = []): OperationInterface
 *     {
 *         $this->counter++;
 *         return new Operation(
 *             id: Uuid::fromString(sprintf('00000000-0000-7000-8000-%012d', $this->counter)),
 *             command: $command,
 *             startedAt: new DateTimeImmutable('2024-01-01 00:00:00'),
 *             attributes: $attributes,
 *         );
 *     }
 * }
 * ```
 */
interface OperationFactoryInterface
{
    /**
     * Creates a new operation instance.
     *
     * @return OperationInterface New operation ready for use
     */
    public function create(object $command, array $attributes = []): OperationInterface;
}
