<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Metadata;

use Attribute;
use Componenta\CQRS\Map\CqrsMapProviderInterface;
use Componenta\CQRS\Map\Exception\InvalidCqrsMapException;
use ReflectionClass;
use Throwable;

final class CompiledCommandMetadataProvider implements CommandMetadataProviderInterface
{
    public function __construct(
        private readonly CqrsMapProviderInterface $mapProvider,
        private readonly CommandMetadataProviderInterface $fallback
            = new ReflectionCommandMetadataProvider(),
    ) {
    }

    /**
     * @template T of object
     * @param object|string $command
     * @param class-string<T> $attribute
     * @return T|null
     */
    public function get(object|string $command, string $attribute): ?object
    {
        $command = self::className($command);
        $map = $this->mapProvider->map();
        $descriptor = $map->commandMetadata($command, $attribute);

        if ($descriptor !== null) {
            return self::materialize(
                $attribute,
                $descriptor->arguments,
            );
        }

        if ($map->isKnownCommand($command)) {
            return null;
        }

        return $this->fallback->get(
            $command,
            $attribute,
        );
    }

    public function isKnown(object|string $command): bool
    {
        return $this->mapProvider->map()->isKnownCommand(self::className($command));
    }

    /**
     * @template T of object
     * @param class-string<T> $attribute
     * @param array<int|string, mixed> $arguments
     * @return T
     */
    private static function materialize(string $attribute, array $arguments): object
    {
        self::assertAttributeClass($attribute);

        try {
            return new $attribute(...$arguments);
        } catch (Throwable $exception) {
            throw new InvalidCqrsMapException(sprintf(
                'Cannot materialize command metadata attribute "%s": %s',
                $attribute,
                $exception->getMessage(),
            ), previous: $exception);
        }
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
