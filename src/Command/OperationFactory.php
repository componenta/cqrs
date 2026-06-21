<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command;

/**
 * Default operation factory implementation for code that creates operations explicitly.
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
     * @param array $attributes
     * @return OperationInterface New operation instance
     */
    public function create(object $command, array $attributes = []): OperationInterface
    {
        return Operation::create($command, $attributes);
    }
}
