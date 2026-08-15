<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Metadata;

use Attribute;
use Componenta\CQRS\Map\Exception\InvalidCqrsMapException;
use Componenta\Reflection\Reflection;
use ReflectionClass;
use Throwable;

final class ReflectionCommandMetadataProvider implements CommandMetadataProviderInterface
{
    public function get(object|string $command, string $attribute): ?object
    {
        self::assertAttributeClass($attribute);

        $reflection = Reflection::class(self::className($command));

        if ($reflection === null) {
            return null;
        }

        $attributes = $reflection->getAttributes($attribute);

        if (count($attributes) > 1) {
            throw new InvalidCqrsMapException(sprintf(
                'Command "%s" has repeated metadata attribute "%s"; only one descriptor is supported.',
                $reflection->getName(),
                $attribute,
            ));
        }

        if ($attributes === []) {
            return null;
        }

        try {
            return $attributes[0]->newInstance();
        } catch (Throwable $exception) {
            throw new InvalidCqrsMapException(sprintf(
                'Cannot materialize command metadata attribute "%s": %s',
                $attribute,
                $exception->getMessage(),
            ), previous: $exception);
        }
    }

    public function isKnown(object|string $command): bool
    {
        return Reflection::class(self::className($command)) !== null;
    }

    /**
     * @param class-string $attribute
     */
    private static function assertAttributeClass(string $attribute): void
    {
        if (!class_exists($attribute)) {
            throw new InvalidCqrsMapException(sprintf(
                'Command metadata attribute class "%s" does not exist.',
                $attribute,
            ));
        }

        $reflection = new ReflectionClass($attribute);
        $declarations = $reflection->getAttributes(Attribute::class);

        if ($declarations === []) {
            throw new InvalidCqrsMapException(sprintf(
                'Command metadata class "%s" is not declared with #[Attribute].',
                $attribute,
            ));
        }

        if (($declarations[0]->newInstance()->flags & Attribute::TARGET_CLASS) === 0) {
            throw new InvalidCqrsMapException(sprintf(
                'Command metadata attribute "%s" must allow class targets.',
                $attribute,
            ));
        }
    }

    private static function className(object|string $command): string
    {
        return is_object($command) ? $command::class : $command;
    }
}
