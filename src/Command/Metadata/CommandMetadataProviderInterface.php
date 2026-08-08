<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Metadata;

interface CommandMetadataProviderInterface
{
    /**
     * @template T of object
     * @param object|string $command
     * @param class-string<T> $attribute
     * @return T|null
     */
    public function get(object|string $command, string $attribute): ?object;

    public function isKnown(object|string $command): bool;
}
