<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Metadata;

use Componenta\CQRS\Command\Attribute\Async;
use Componenta\CQRS\Command\Attribute\Lock;
use Componenta\CQRS\Command\Attribute\Retry;

/**
 * Command attribute provider backed by discovery-compiled descriptors.
 *
 * Known command classes are treated as complete: if an attribute descriptor is
 * absent for that class, the attribute is absent. Unknown classes are delegated
 * to the reflection provider so ad-hoc commands and tests keep working.
 */
final class CompiledCommandAttributeProvider implements CommandAttributeProviderInterface
{
    /** @var array<class-string, true> */
    private readonly array $known;

    /** @var array<class-string, array<string, array<string, mixed>>> */
    private readonly array $attributes;

    /** @var array<string, Async|null> */
    private array $async = [];

    /** @var array<string, Retry|null> */
    private array $retry = [];

    /** @var array<string, Lock|null> */
    private array $lock = [];

    /**
     * @param array{known?: array<class-string, true>, attributes?: array<class-string, array<string, array<string, mixed>>>} $map
     */
    public function __construct(
        array $map,
        private readonly CommandAttributeProviderInterface $fallback = new ReflectionCommandAttributeProvider(),
    ) {
        $known = $map['known'] ?? [];
        $attributes = $map['attributes'] ?? [];

        $this->known = is_array($known) ? $known : [];
        $this->attributes = is_array($attributes) ? $attributes : [];
    }

    public function async(object|string $command): ?Async
    {
        $class = $this->className($command);

        if (array_key_exists($class, $this->async)) {
            return $this->async[$class];
        }

        $descriptor = $this->descriptor($class, 'async');

        return $this->async[$class] = is_array($descriptor)
            ? new Async(
                transport: (string) ($descriptor['transport'] ?? 'default'),
                delay: (int) ($descriptor['delay'] ?? 0),
            )
            : $this->fallback($class, 'async');
    }

    public function retry(object|string $command): ?Retry
    {
        $class = $this->className($command);

        if (array_key_exists($class, $this->retry)) {
            return $this->retry[$class];
        }

        $descriptor = $this->descriptor($class, 'retry');

        return $this->retry[$class] = is_array($descriptor)
            ? new Retry(
                attempts: (int) ($descriptor['attempts'] ?? 3),
                delayMs: (int) ($descriptor['delayMs'] ?? 100),
                multiplier: (float) ($descriptor['multiplier'] ?? 1.0),
                maxDelayMs: (int) ($descriptor['maxDelayMs'] ?? 10000),
            )
            : $this->fallback($class, 'retry');
    }

    public function lock(object|string $command): ?Lock
    {
        $class = $this->className($command);

        if (array_key_exists($class, $this->lock)) {
            return $this->lock[$class];
        }

        $descriptor = $this->descriptor($class, 'lock');

        return $this->lock[$class] = is_array($descriptor)
            ? new Lock(
                key: (string) ($descriptor['key'] ?? ''),
                ttl: (float) ($descriptor['ttl'] ?? 300.0),
                blocking: (bool) ($descriptor['blocking'] ?? true),
            )
            : $this->fallback($class, 'lock');
    }

    private function className(object|string $command): string
    {
        return is_object($command) ? $command::class : $command;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function descriptor(string $class, string $name): ?array
    {
        $descriptor = $this->attributes[$class][$name] ?? null;

        if (is_array($descriptor)) {
            return $descriptor;
        }

        return isset($this->known[$class]) ? null : null;
    }

    private function fallback(string $class, string $name): Async|Retry|Lock|null
    {
        if (isset($this->known[$class])) {
            return null;
        }

        return match ($name) {
            'async' => $this->fallback->async($class),
            'retry' => $this->fallback->retry($class),
            'lock' => $this->fallback->lock($class),
        };
    }
}
