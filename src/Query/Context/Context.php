<?php

declare(strict_types=1);

namespace Componenta\CQRS\Query\Context;

/**
 * Default immutable {@see ContextInterface} - stores attributes in an array.
 *
 * Returned by {@see \Componenta\CQRS\Query\QueryBus} when an array is passed at
 * dispatch time; also the canonical type received by middleware.
 */
final readonly class Context implements ContextInterface
{
    /** @param array<string, mixed> $attributes */
    public function __construct(public array $attributes = []) {}

    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    public function hasAttribute(string $name): bool
    {
        return array_key_exists($name, $this->attributes);
    }

    public function withAttribute(string $name, mixed $value): static
    {
        return new self([...$this->attributes, $name => $value]);
    }

    public function withAttributes(array $attributes): static
    {
        return new self([...$this->attributes, ...$attributes]);
    }

    public function withoutAttribute(string $name): static
    {
        $attributes = $this->attributes;
        unset($attributes[$name]);

        return new self($attributes);
    }
}
