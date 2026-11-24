<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Metadata;

use Attribute;
use Componenta\CQRS\Command\Exception\InvalidCommandMetadataException;
use ReflectionClass;
use Throwable;

final class ReflectionCommandMetadataProvider implements CommandMetadataProviderInterface
{
    /**
     * @template T of object
     * @param class-string<T> $attribute
     * @return T|null
     * @throws InvalidCommandMetadataException If the metadata cannot be read or materialized.
     */
    public function get(object|string $command, string $attribute): ?object
    {
        try {
            self::assertAttributeClass($attribute);
            $class = is_object($command) ? $command::class : $command;
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
            return $attributes === [] ? null : $attributes[0]->newInstance();
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
