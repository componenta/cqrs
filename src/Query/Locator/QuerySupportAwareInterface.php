<?php

declare(strict_types=1);

namespace Componenta\CQRS\Query\Locator;

interface QuerySupportAwareInterface
{
    /** Check whether this locator can resolve the query. */
    public function supports(object $query): bool;
}
