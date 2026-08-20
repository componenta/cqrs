<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command;

/** Factory for creating command operation instances. */
interface OperationFactoryInterface
{
    /**
     * Creates a new operation for one dispatch.
     *
     * Implementations that construct Operation directly should set createdAt to
     * the operation creation time; execution start is not part of this contract.
     *
     * @param array<string, mixed> $attributes
     */
    public function create(object $command, array $attributes = []): OperationInterface;
}
