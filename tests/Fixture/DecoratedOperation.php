<?php

declare(strict_types=1);

namespace Componenta\CQRS\Tests\Fixture;

use Componenta\CQRS\Command\OperationInterface;
use Componenta\CQRS\Command\OperationResult;
use DateTimeImmutable;
use Componenta\Identity\UuidInterface;

/** An alternative operation implementation that delegates the immutable value contract. */
final class DecoratedOperation implements OperationInterface
{
    public UuidInterface $id { get => $this->operation->id; }
    public DateTimeImmutable $createdAt { get => $this->operation->createdAt; }
    public ?OperationResult $result { get => $this->operation->result; }
    public object $command { get => $this->operation->command; }

    /** @var array<string, mixed> */
    public array $attributes { get => $this->operation->attributes; }

    public function __construct(private readonly OperationInterface $operation)
    {
    }

    public function withResult(OperationResult $result): OperationInterface
    {
        return new self($this->operation->withResult($result));
    }

    public function withAttributes(array $attributes): OperationInterface
    {
        return new self($this->operation->withAttributes($attributes));
    }

    public function withAttribute(string $name, mixed $value): OperationInterface
    {
        return new self($this->operation->withAttribute($name, $value));
    }

    public function withoutAttribute(string $name): OperationInterface
    {
        return new self($this->operation->withoutAttribute($name));
    }
}
