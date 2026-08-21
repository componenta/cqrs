<?php

declare(strict_types=1);

namespace Componenta\CQRS\Map;

use Closure;
use Componenta\CQRS\Map\Exception\InvalidCqrsMapException;

final readonly class CommandMetadataDescriptor
{
    private const int MAX_COMPARISON_DEPTH = 64;

    /**
     * @param non-empty-string $attribute
     * @param array<int|string, mixed> $arguments
     */
    public function __construct(
        public string $attribute,
        public array $arguments = [],
    ) {
        if (trim($this->attribute) === '') {
            throw new InvalidCqrsMapException(
                'Command metadata attribute must be a non-empty class-string.',
            );
        }
    }

    /**
     * @param non-empty-string $attribute
     * @param array<array-key, mixed> $descriptor
     */
    public static function fromArray(string $attribute, array $descriptor): self
    {
        if (array_keys($descriptor) !== ['arguments'] || !is_array($descriptor['arguments'])) {
            throw new InvalidCqrsMapException(sprintf(
                'Command metadata descriptor "%s" must contain exactly an "arguments" array.',
                $attribute,
            ));
        }

        /** @var array<int|string, mixed> $arguments */
        $arguments = $descriptor['arguments'];

        return new self($attribute, $arguments);
    }

    /**
     * @return array{arguments: array<int|string, mixed>}
     */
    public function toArray(): array
    {
        return ['arguments' => $this->arguments];
    }

    public function equals(self $other): bool
    {
        $seenObjects = [];

        return $this->attribute === $other->attribute
            && self::valuesEquivalent(
                $this->arguments,
                $other->arguments,
                $seenObjects,
            );
    }

    /**
     * Compare metadata values without invoking user serialization/property hooks.
     *
     * @param array<string, true> $seenObjects
     */
    private static function valuesEquivalent(
        mixed $left,
        mixed $right,
        array &$seenObjects,
        int $depth = 0,
    ): bool {
        if ($depth > self::MAX_COMPARISON_DEPTH || get_debug_type($left) !== get_debug_type($right)) {
            return false;
        }

        if (is_array($left)) {
            if (!is_array($right) || array_keys($left) !== array_keys($right)) {
                return false;
            }

            foreach ($left as $key => $value) {
                if (!self::valuesEquivalent($value, $right[$key], $seenObjects, $depth + 1)) {
                    return false;
                }
            }

            return true;
        }

        if (is_object($left)) {
            if (!is_object($right) || $left::class !== $right::class) {
                return false;
            }

            // A closure's behavior is not represented by stored object state.
            if ($left instanceof Closure) {
                return $left === $right;
            }

            $pair = spl_object_id($left) . ':' . spl_object_id($right);

            if (isset($seenObjects[$pair])) {
                return true;
            }

            $seenObjects[$pair] = true;

            return self::valuesEquivalent(
                (array) $left,
                (array) $right,
                $seenObjects,
                $depth + 1,
            );
        }

        return $left === $right;
    }
}
