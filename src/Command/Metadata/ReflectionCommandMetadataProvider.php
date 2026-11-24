<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Metadata;

use Componenta\CQRS\Exception\ExceptionInterface;
use Attribute;
use Componenta\CQRS\Command\Exception\InvalidCommandMetadataException;
use ReflectionAttribute;
use ReflectionClass;
use Throwable;

final class ReflectionCommandMetadataProvider implements CommandMetadataProviderInterface
{
    private const int MAX_CACHED_LOOKUPS = 512;

    /** @var array<string, array<string, ReflectionAttribute<object>|null>> */
    private array $lookups = [];
    private int $cachedLookups = 0;

    /**
     * @template T of object
     * @param class-string<T> $attribute
     * @return T|null
     * @throws ExceptionInterface If the metadata cannot be read or materialized.
     */
    public function get(object|string $command, string $attribute): ?object
    {
        try {
            $class = is_object($command) ? $command::class : $command;
            if (isset($this->lookups[$attribute]) && array_key_exists($class, $this->lookups[$attribute])) {
                /** @var ReflectionAttribute<T>|null $cached */
                $cached = $this->lookups[$attribute][$class];

                return $cached?->newInstance();
            }

            self::assertAttributeClass($attribute);
            if (!class_exists($class)) {
                return null;
            }
            /** @var class-string<T> $normalizedAttribute */
            $normalizedAttribute = ltrim($attribute, '\\');
            $attributes = new ReflectionClass($class)->getAttributes($normalizedAttribute);
            if (count($attributes) > 1) {
                throw new InvalidCommandMetadataException(sprintf(
                    'Command "%s" has repeated metadata attribute "%s"; only one instance is supported.',
                    $class,
                    $attribute,
                ));
            }
            $descriptor = $attributes[0] ?? null;

            // Loaded class declarations cannot change. Cache only reflection
            // metadata, never instantiated attributes or their arguments.
            // An unavailable command class or invalid attribute is not cached.
            if ($this->cachedLookups < self::MAX_CACHED_LOOKUPS) {
                $this->lookups[$attribute][$class] = $descriptor;
                ++$this->cachedLookups;
            }

            return $descriptor?->newInstance();
        } catch (InvalidCommandMetadataException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new InvalidCommandMetadataException(sprintf(
                'Cannot materialize command metadata attribute "%s": %s',
                $attribute,
                $exception->getMessage(),
            ), previous: $exception);
        }
    }

    private static function assertAttributeClass(string $attribute): void
    {
        if (!class_exists($attribute)) {
            throw new InvalidCommandMetadataException(sprintf(
                'Command metadata attribute class "%s" does not exist.',
                $attribute,
            ));
        }
        $declarations = new ReflectionClass($attribute)->getAttributes(Attribute::class);
        if ($declarations === []) {
            throw new InvalidCommandMetadataException(sprintf(
                'Command metadata class "%s" is not declared with #[Attribute].',
                $attribute,
            ));
        }
        if (($declarations[0]->newInstance()->flags & Attribute::TARGET_CLASS) === 0) {
            throw new InvalidCommandMetadataException(sprintf(
                'Command metadata attribute "%s" must allow class targets.',
                $attribute,
            ));
        }
    }
}
