<?php

declare(strict_types=1);

namespace Componenta\CQRS\Query\Context;

use InvalidArgumentException;

/**
 * Default immutable {@see ContextInterface} - stores attributes in an array.
 *
 * Returned by {@see \Componenta\CQRS\Query\QueryBus} when an array is passed at
 * dispatch time; also the canonical type received by middleware.
 */
final readonly class Context implements ContextInterface
{
    /** @param array<string, mixed> $attributes */
    public function __construct(public array $attributes = [])
    {
        self::assertAttributes($attributes);
    }

    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return array_key_exists($name, $this->attributes)
            ? $this->attributes[$name]
            : $default;
    }

    public function hasAttribute(string $name): bool
    {
        return array_key_exists($name, $this->attributes);
    }

    public function withAttribute(string $name, mixed $value): static
    {
        self::assertAttributeName($name);

        return new self([...$this->attributes, $name => $value]);
    }

    public function withAttributes(array $attributes): static
    {
        return new self($attributes);
    }

    public function withoutAttribute(string $name): static
    {
        self::assertAttributeName($name);

        $attributes = $this->attributes;
        unset($attributes[$name]);

        return new self($attributes);
    }

    /** @param array<array-key, mixed> $attributes */
    private static function assertAttributes(array $attributes): void
    {
        foreach ($attributes as $name => $_) {
            if (!is_string($name) || trim($name) === '') {
                throw new InvalidArgumentException(
                    'Query context attribute names must be non-empty strings.',
                );
            }
        }
    }

    private static function assertAttributeName(string $name): void
    {
        if (trim($name) === '') {
            throw new InvalidArgumentException(
                'Query context attribute name must be a non-empty string.',
            );
        }

        if (!is_string(array_key_first([$name => true]))) {
            throw new InvalidArgumentException(sprintf(
                'Query context attribute name "%s" is converted by PHP to an integer array key.',
                $name,
            ));
        }
    }
}
