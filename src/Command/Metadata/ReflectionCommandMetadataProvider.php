<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Metadata;

use Attribute;
use Componenta\CQRS\Map\Exception\InvalidCqrsMapException;
use Componenta\Reflection\Reflection;
use ReflectionClass;

final class ReflectionCommandMetadataProvider implements CommandMetadataProviderInterface
{
    public function get(object|string $command, string $attribute): ?object
    {
        self::assertAttributeClass($attribute);

        $reflection = Reflection::class(self::className($command));

        if ($reflection === null) {
            return null;
        }

        return Reflection::getFirstMetadata($reflection, $attribute);
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

        if ((new ReflectionClass($attribute))->getAttributes(Attribute::class) === []) {
            throw new InvalidCqrsMapException(sprintf(
                'Command metadata class "%s" is not declared with #[Attribute].',
                $attribute,
            ));
        }
    }

    private static function className(object|string $command): string
    {
        return is_object($command) ? $command::class : $command;
    }
}
