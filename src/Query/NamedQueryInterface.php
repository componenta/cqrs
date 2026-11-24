<?php

declare(strict_types=1);

namespace Componenta\CQRS\Query;

/** Supplies an explicit logical query name instead of its PHP class name. */
interface NamedQueryInterface
{
    /**
     * Name used by the default resolver. It may vary between instances of a
     * class and is used without trimming or case normalization. A routable
     * name must match a valid registration (not empty or whitespace-only).
     */
    public string $queryName { get; }
}
