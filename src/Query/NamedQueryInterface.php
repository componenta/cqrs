<?php

declare(strict_types=1);

namespace Componenta\CQRS\Query;

interface NamedQueryInterface
{
    public string $queryName { get; }
}
