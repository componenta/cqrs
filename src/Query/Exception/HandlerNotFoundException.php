<?php

declare(strict_types=1);

namespace Componenta\CQRS\Query\Exception;

use Componenta\CQRS\Query\NamedQueryInterface;
use RuntimeException;

class HandlerNotFoundException extends RuntimeException
{
    public readonly string $queryName;

    public function __construct(
        public readonly object $query,
        ?string $message = null,
        ?string $queryName = null,
    ) {
        $this->queryName = $queryName
            ?? ($query instanceof NamedQueryInterface ? $query->queryName : $query::class);

        parent::__construct($message ?? 'No Handler found for query ' . $this->queryName);
    }
}