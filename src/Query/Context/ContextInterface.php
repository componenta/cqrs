<?php

declare(strict_types=1);

namespace Componenta\CQRS\Query\Context;

/**
 * Immutable key-value context propagated through the query middleware chain.
 *
 * Carries per-dispatch data (skip flags, actor overrides, request metadata).
 * All mutation-like methods return a new instance.
 */
interface ContextInterface
{
    /** @var array<string, mixed> */
    public array $attributes { get; }

    public function getAttribute(string $name, mixed $default = null): mixed;

    public function hasAttribute(string $name): bool;

    public function withAttribute(string $name, mixed $value): static;

    /**
     * @param array<string, mixed> $attributes Merged on top of the existing attributes.
     */
    public function withAttributes(array $attributes): static;

    public function withoutAttribute(string $name): static;
}
