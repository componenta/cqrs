<?php

namespace Componenta\CQRS\Query\Locator;

interface QuerySupportAwareInterface
{
    /**
     * Check if handler exists for query.
     */
    public function supports(object $query): bool;
}