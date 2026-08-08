<?php

declare(strict_types=1);

namespace Componenta\CQRS\Map;

use Componenta\CQRS\Map\Exception\InvalidCqrsMapException;

final readonly class CommandMetadataDescriptor
{
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
        return $this->attribute === $other->attribute
            && $this->arguments === $other->arguments;
    }
}
