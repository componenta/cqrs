<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Middleware;

use Attribute;
use InvalidArgumentException;

/**
 * Declares hard ordering constraints for command middleware.
 *
 * Constraints are evaluated only when the referenced middleware is present in
 * the same command bus. They are validated by CommandBus before the pipeline is
 * compiled, so manual and container-created buses have identical behavior.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class MiddlewareOrder
{
    /**
     * @param list<class-string> $before Middleware types that this middleware must precede.
     * @param list<class-string> $after Middleware types that this middleware must follow.
     */
    public function __construct(
        public array $before = [],
        public array $after = [],
    ) {
        self::assertTypes($before, 'before');
        self::assertTypes($after, 'after');
    }

    /** @param array<array-key, mixed> $types */
    private static function assertTypes(array $types, string $relation): void
    {
        if (!array_is_list($types)) {
            throw new InvalidArgumentException(sprintf(
                'Middleware order "%s" constraints must be a list.',
                $relation,
            ));
        }

        foreach ($types as $index => $type) {
            if (!is_string($type) || trim($type) === '') {
                throw new InvalidArgumentException(sprintf(
                    'Middleware order "%s" constraint at index %d must be a non-empty class name.',
                    $relation,
                    $index,
                ));
            }
        }
    }
}
