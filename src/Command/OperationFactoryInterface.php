<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command;

use Componenta\CQRS\Exception\InvalidArgumentException;
use Throwable;

/** Creates command operations before processing begins. */
interface OperationFactoryInterface
{
    /**
     * Creates a new operation for one dispatch, with a unique identifier,
     * the supplied command and attributes, and no result.
     * createdAt records creation time, not handler start time; the default timezone is UTC.
     *
     * @param array<string, mixed> $attributes
     * @throws InvalidArgumentException If attribute names violate OperationInterface.
     * @throws Throwable Other failures during operation creation.
     */
    public function create(object $command, array $attributes = []): OperationInterface;
}
