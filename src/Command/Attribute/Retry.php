<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Attribute;

use Attribute;
use InvalidArgumentException;

/**
 * Marks command as retryable on transient failures.
 *
 * @example
 * ```php
 * #[Retry(attempts: 3, delayMs: 100)]
 * final readonly class ProcessPaymentCommand
 * {
 *     public function __construct(
 *         public int $orderId,
 *         public int $amount,
 *     ) {}
 * }
 * ```
 *
 * @example
 * ```php
 * // Exponential backoff: 100ms, 200ms, 400ms
 * #[Retry(attempts: 3, delayMs: 100, multiplier: 2.0)]
 * final readonly class SyncExternalServiceCommand {}
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Retry
{
    public int $delayMs;

    /**
     * @param int $attempts Maximum number of attempts (including initial)
     * @param int $delayMs Delay between attempts in milliseconds
     * @param float $multiplier Multiplier for exponential backoff (1.0 = fixed delay)
     * @param int $maxDelayMs Maximum delay cap for exponential backoff
     */
    public function __construct(
        public int $attempts = 3,
        int $delayMs = 100,
        public float $multiplier = 1.0,
        public int $maxDelayMs = 10000,
    ) {
        if ($attempts < 1) {
            throw new InvalidArgumentException('Attempts must be at least 1');
        }

        if ($delayMs < 0) {
            throw new InvalidArgumentException('Delay must be non-negative');
        }

        if ($multiplier < 1.0) {
            throw new InvalidArgumentException('Multiplier must be at least 1.0');
        }

        if ($maxDelayMs < $delayMs) {
            throw new InvalidArgumentException('Max delay must be greater than or equal to delay');
        }

        $this->delayMs = $delayMs;
    }
}