<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Locator;

use Componenta\CQRS\Command\Exception\HandlerNotFoundException;

/**
 * Composite locator delegating to first supporting locator.
 *
 * Iterates through support-aware locators, checking supports()
 * to find appropriate delegate. Falls back to default locator
 * if none match.
 *
 * @example
 * ```php
 * $collector = new LocatorCollector(
 *     fallback: $containerLocator,
 *     $billingLocator,
 *     $shippingLocator,
 * );
 *
 * // BillingCommand -> $billingLocator (supports() = true)
 * // ShippingCommand -> $shippingLocator (supports() = true)
 * // OtherCommand -> $containerLocator (fallback)
 * ```
 */
final readonly class LocatorCollector implements CommandHandlerLocatorInterface
{
    /** @var array<CommandHandlerLocatorInterface&CommandSupportAwareInterface> */
    private array $locators;

    /**
     * @param CommandHandlerLocatorInterface $fallback Default locator when no other matches
     * @param CommandHandlerLocatorInterface&CommandSupportAwareInterface ...$locators Support-aware locators
     */
    public function __construct(
        private CommandHandlerLocatorInterface $fallback,
        CommandHandlerLocatorInterface&CommandSupportAwareInterface ...$locators,
    ) {
        $this->locators = $locators;
    }

    /**
     * @template T of object
     * @param T $command
     * @return callable(T): mixed
     *
     * @throws HandlerNotFoundException if handler for command not found
     */
    public function locateFor(object $command): callable
    {
        foreach ($this->locators as $locator) {
            if ($locator->supports($command)) {
                return $locator->locateFor($command);
            }
        }

        return $this->fallback->locateFor($command);
    }
}