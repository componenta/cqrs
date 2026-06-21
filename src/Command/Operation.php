<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command;

use DateTimeImmutable;
use DateTimeZone;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final  readonly class Operation implements OperationInterface
{
    public function __construct(
        public UuidInterface $id,
        public object $command,
        public DateTimeImmutable $startedAt,
        private(set) array $attributes = [],
        public ?OperationResult  $result = null,
    ) {}


    public static function create(object $command, array $attributes = []): self
    {
        return new self(
            id: Uuid::uuid7(),
            command: $command,
            startedAt: new DateTimeImmutable('now', new DateTimeZone('UTC')),
            attributes: $attributes,
        );
    }

    public function withResult(OperationResult $result): OperationInterface
    {
        if ($this->result !== null) {
            throw new \RuntimeException('Operation already has result');
        }

        return new self(
            id: $this->id,
            command: $this->command,
            startedAt: $this->startedAt,
            attributes: $this->attributes,
            result: $result,
        );
    }

    public function withAttributes(array $attributes): OperationInterface
    {
        return new self(
            id: $this->id,
            command: $this->command,
            startedAt: $this->startedAt,
            attributes: $attributes,
            result: $this->result,
        );
    }

    public function withAttribute(string $name, mixed $value): OperationInterface
    {
        return new self(
            id: $this->id,
            command: $this->command,
            startedAt: $this->startedAt,
            attributes: [...$this->attributes, $name => $value],
            result: $this->result,
        );
    }

    public function withoutAttribute(string $name): OperationInterface
    {
        $attributes = $this->attributes;
        unset($attributes[$name]);

        return new self(
            id: $this->id,
            command: $this->command,
            startedAt: $this->startedAt,
            attributes: $attributes,
            result: $this->result,
        );
    }
}
