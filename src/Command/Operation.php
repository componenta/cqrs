<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final readonly class Operation implements OperationInterface
{
    /** @var array<string, mixed> */
    public private(set) array $attributes;

    /**
     * @param array<string, mixed> $attributes
     * @throws InvalidArgumentException If an attribute name is invalid.
     */
    public function __construct(
        public UuidInterface $id,
        public object $command,
        public DateTimeImmutable $createdAt,
        array $attributes = [],
        public ?OperationResult $result = null,
    ) {
        // Copy top-level values, detaching PHP references while validating keys.
        $snapshot = [];
        foreach ($attributes as $name => $value) {
            if (!is_string($name) || trim($name) === '') {
                throw new InvalidArgumentException(
                    'Operation attribute names must be non-empty strings.',
                );
            }
            $snapshot[$name] = $value;
        }
        $this->attributes = $snapshot;
    }

    /**
     * @param array<string, mixed> $attributes
     * @throws InvalidArgumentException If an attribute name is invalid.
     */
    public static function create(object $command, array $attributes = []): self
    {
        return new self(
            id: Uuid::uuid7(),
            command: $command,
            createdAt: new DateTimeImmutable('now', new DateTimeZone('UTC')),
            attributes: $attributes,
        );
    }

    public function withResult(OperationResult $result): OperationInterface
    {
        if ($this->result !== null) {
            throw new \RuntimeException('Operation already has result');
        }

        return clone($this, ['result' => $result]);
    }

    public function withAttributes(array $attributes): OperationInterface
    {
        return new self(
            id: $this->id,
            command: $this->command,
            createdAt: $this->createdAt,
            attributes: $attributes,
            result: $this->result,
        );
    }

    public function withAttribute(string $name, mixed $value): OperationInterface
    {
        self::assertAttributeName($name);

        return new self(
            id: $this->id,
            command: $this->command,
            createdAt: $this->createdAt,
            attributes: [...$this->attributes, $name => $value],
            result: $this->result,
        );
    }

    public function withoutAttribute(string $name): OperationInterface
    {
        self::assertAttributeName($name);

        $attributes = $this->attributes;
        unset($attributes[$name]);

        return new self(
            id: $this->id,
            command: $this->command,
            createdAt: $this->createdAt,
            attributes: $attributes,
            result: $this->result,
        );
    }

    private static function assertAttributeName(string $name): void
    {
        if (trim($name) === '') {
            throw new InvalidArgumentException(
                'Operation attribute name must be a non-empty string.',
            );
        }

        if (!is_string(array_key_first([$name => true]))) {
            throw new InvalidArgumentException(sprintf(
                'Operation attribute name "%s" is converted by PHP to an integer array key.',
                $name,
            ));
        }
    }
}
