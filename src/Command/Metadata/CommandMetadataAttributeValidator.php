<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Metadata;

use Attribute;
use Componenta\CQRS\Map\Exception\InvalidCqrsMapException;
use ReflectionClass;

/** @internal */
final class CommandMetadataAttributeValidator
{
    /** @var array<class-string, true> */
    private array $validated = [];

    /** @param class-string $attribute */
    public function validate(string $attribute): void
    {
        if (isset($this->validated[$attribute])) {
            return;
        }

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

        $this->validated[$attribute] = true;
    }
}
