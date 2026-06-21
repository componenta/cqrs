<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Metadata;

use Componenta\CQRS\Command\Attribute\Async;
use Componenta\CQRS\Command\Attribute\Lock;
use Componenta\CQRS\Command\Attribute\Retry;
use Componenta\Reflection\Reflection;

final class ReflectionCommandAttributeProvider implements CommandAttributeProviderInterface
{
    public function async(object|string $command): ?Async
    {
        return $this->first($command, Async::class);
    }

    public function retry(object|string $command): ?Retry
    {
        return $this->first($command, Retry::class);
    }

    public function lock(object|string $command): ?Lock
    {
        return $this->first($command, Lock::class);
    }

    /**
     * @template T of object
     * @param class-string<T> $attribute
     * @return T|null
     */
    private function first(object|string $command, string $attribute): ?object
    {
        $class = is_object($command) ? $command::class : $command;
        $reflection = Reflection::class($class);

        if ($reflection === null) {
            return null;
        }

        /** @var T|null */
        return Reflection::getFirstMetadata($reflection, $attribute);
    }
}
