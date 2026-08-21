<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Metadata;

use Componenta\CQRS\Map\CqrsMapProviderInterface;
use Componenta\CQRS\Map\Exception\InvalidCqrsMapException;
use Throwable;

/**
 * Reads command metadata strictly from the active CQRS map.
 *
 * Runtime reflection is intentionally not used as a fallback. This keeps
 * development, compiled production, workers, and manually constructed
 * containers on the same metadata contract. Applications that deliberately
 * want reflection may bind ReflectionCommandMetadataProvider explicitly.
 */
final readonly class CompiledCommandMetadataProvider implements CommandMetadataProviderInterface
{
    private CommandMetadataAttributeValidator $attributeValidator;

    public function __construct(
        private CqrsMapProviderInterface $mapProvider,
    ) {
        $this->attributeValidator = new CommandMetadataAttributeValidator();
    }

    /**
     * @template T of object
     * @param object|string $command
     * @param class-string<T> $attribute
     * @return T|null
     */
    public function get(object|string $command, string $attribute): ?object
    {
        $descriptor = $this->mapProvider->map()->commandMetadata(
            self::className($command),
            $attribute,
        );

        if ($descriptor === null) {
            return null;
        }

        $this->attributeValidator->validate($attribute);

        try {
            return new $attribute(...$descriptor->arguments);
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
        return $this->mapProvider->map()->isKnownCommand(
            self::className($command),
        );
    }

    private static function className(object|string $command): string
    {
        return is_object($command) ? $command::class : $command;
    }
}
