<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Metadata;

use Componenta\CQRS\Command\Exception\InvalidCommandMetadataException;

/** Reads command metadata independently of handler registration. */
interface CommandMetadataProviderInterface
{
    /**
     * Returns the requested metadata attribute, or null if the command class
     * is unavailable or the requested attribute is absent.
     *
     * The requested attribute must be a valid class-target attribute.
     * The provider returns at most one instance of that attribute.
     *
     * @template T of object
     * @param object|string $command A command instance or its class name.
     * @param class-string<T> $attribute
     * @return T|null
     * @throws InvalidCommandMetadataException If the attribute type or declaration is invalid,
     *         repeated, or cannot be materialized.
     * @throws \Throwable Other failures during metadata retrieval.
     */
    public function get(object|string $command, string $attribute): ?object;
}
