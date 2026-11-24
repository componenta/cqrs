<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command;

use Componenta\CQRS\Clock\UtcClock;
use Componenta\CQRS\Exception\InvalidArgumentException;
use Componenta\Identity\UuidFactory;
use Componenta\Identity\UuidFactoryInterface;
use Psr\Clock\ClockInterface;

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
    public function __construct(
        private ClockInterface $clock = new UtcClock(),
        private UuidFactoryInterface $uuidFactory = new UuidFactory(),
    ) {}

    /**
     * Creates a new operation using the configured UUID generator and clock (UUIDv7 and UTC by default).
     *
     * @param object $command
     * @param array<string, mixed> $attributes
     * @return OperationInterface New operation instance
     * @throws InvalidArgumentException If an attribute name is invalid.
     * @throws \Throwable Other failures during operation creation.
     */
    public function create(object $command, array $attributes = []): OperationInterface
    {
        return Operation::create(
            command: $command,
            attributes: $attributes,
            createdAt: $this->clock->now(),
            id: $this->uuidFactory->generate(),
        );
    }
}
