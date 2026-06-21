<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Locator;

/**
 * Indicates locator can check command support before locating.
 *
 * Used by composite locators to find appropriate delegate.
 */
interface CommandSupportAwareInterface
{
    /**
     * Checks if this locator supports the given command.
     *
     * @param object $command Command to check
     * @return bool True if locator can locate handler for this command
     */
    public function supports(object $command): bool;
}