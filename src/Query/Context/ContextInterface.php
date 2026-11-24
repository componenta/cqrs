<?php

declare(strict_types=1);

namespace Componenta\CQRS\Query\Context;

use InvalidArgumentException;

/**
 * Immutable key-value context propagated through query middleware.
 *
 * Attribute names are non-empty strings, excluding whitespace-only names
 * and strings that PHP converts to integer array keys. The meaning of each
 * attribute belongs to the middleware that uses it. Attribute values may be
 * mutable objects; this contract does not require deep cloning.
 */
interface ContextInterface
{
    /** @var array<string, mixed> */
    public array $attributes { get; }

    /**
     * Returns the stored value, including null, or the supplied default if
     * the name is absent.
     */
    public function getAttribute(string $name, mixed $default = null): mixed;

    /** Returns true if the name is present, even if its value is null. */
    public function hasAttribute(string $name): bool;

    /**
     * Returns a new context with one attribute added or replaced.
     *
     * @throws InvalidArgumentException If the attribute name is invalid.
     */
    public function withAttribute(string $name, mixed $value): static;

    /**
     * Returns a new context with all attributes replaced.
     *
     * @param array<string, mixed> $attributes
     * @throws InvalidArgumentException If any attribute name is invalid.
     */
    public function withAttributes(array $attributes): static;

    /**
     * Returns a new context without the named attribute.
     * A missing valid name leaves the attribute values unchanged.
     *
     * @throws InvalidArgumentException If the attribute name is invalid.
     */
    public function withoutAttribute(string $name): static;
}
