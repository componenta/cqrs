<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command;

use Componenta\CQRS\Exception\InvalidArgumentException;

/**
 * Default operation factory used by CommandBus.
 *
 * @example
 * ```php
 * $factory = new OperationFactory();
 * $operation = $factory->create($command, ['tenant' => 'main']);
 * ```
 */
final readonly class OperationFactory implements OperationFactoryInterface
{
    /**
     * Creates a new operation with UUID v7 and current timestamp.
     *
     * @param object $command
     * @param array<string, mixed> $attributes
     * @return OperationInterface New operation instance
     * @throws InvalidArgumentException If an attribute name is invalid.
     * @throws \Throwable Other failures during operation creation.
     */
    public function create(object $command, array $attributes = []): OperationInterface
    {
        return Operation::create($command, $attributes);
    }
}
