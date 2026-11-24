<?php

declare(strict_types=1);

namespace Componenta\CQRS\Query\Locator;

use Throwable;

/** Capability check for a locator used in composite handler selection. */
interface QuerySupportAwareInterface
{
    /**
     * Returns whether the locator owns the given query.
     *
     * False is normal and permits the caller to try another locator. True
     * does not guarantee that service resolution or handler execution will
     * succeed. Checking support must not instantiate or invoke the handler.
     * Evaluate the message instance, not only its PHP class.
     *
     * @throws Throwable If checking support fails for reasons other than lack of support.
     */
    public function supports(object $query): bool;
}
