<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Attribute;

use Attribute;

/**
 * Marks class or method as command handler.
 *
 * @example
 * ```php
 * #[AsCommandHandler]
 * final readonly class CreateUserHandler
 * {
 *     public function __invoke(CreateUserCommand $command): int { ... }
 * }
 *
 * final readonly class UserHandlers
 * {
 *     #[AsCommandHandler]
 *     public function create(CreateUserCommand $command): int { ... }
 * }
 *
 * #[AsCommandHandler(CreateUserCommand::class)]
 * final readonly class ExplicitHandler { ... }
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class AsCommandHandler
{
    /**
     * @param string|null $command Command class or name (null = infer from parameter)
     */
    public function __construct(
        public ?string $command = null,
    ) {}
}