<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Attribute;

use Attribute;

/**
 * Marks command for async execution via transport.
 *
 * @example
 * ```php
 * #[Async(transport: 'emails', delay: 60)]
 * final readonly class SendWelcomeEmailCommand
 * {
 *     public function __construct(
 *         public int $userId,
 *     ) {}
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Async
{
    /**
     * @param string $transport Transport name from registry
     * @param int $delay Delay in seconds before command becomes available
     */
    public function __construct(
        public string $transport = 'default',
        public int $delay = 0,
    ) {}
}
