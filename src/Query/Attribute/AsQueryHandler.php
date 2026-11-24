<?php

declare(strict_types=1);

namespace Componenta\CQRS\Query\Attribute;

use Attribute;

/**
 * Marks a class or method as a Query handler.
 *
 * Usage:
 * ```php
 * #[AsQueryHandler(query: GetUserQuery::class)]
 * final class GetUserHandler
 * {
 *     public function __invoke(GetUserQuery $query) { ... }
 * }
 *
 * class AnotherHandler
 * {
 *     #[AsQueryHandler(query: GetOrdersQuery::class)]
 *     public function handle(GetOrdersQuery $query) { ... }
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class AsQueryHandler
{
    /**
     * Optional explicit query class name.
     * If null, attribute discovery can infer the name from the handler's first parameter.
     */
    public function __construct(
        public ?string $query = null
    ) {}
}
