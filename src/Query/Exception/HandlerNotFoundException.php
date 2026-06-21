<?php

namespace Componenta\CQRS\Query\Exception;

class HandlerNotFoundException extends \RuntimeException
{
    public function __construct(
        public readonly object $query,
        ?string $message = null
    ) {
        parent::__construct($message ?? 'No Handler found for query ' . $query::class);
    }
}