<?php

declare(strict_types=1);

namespace Componenta\CQRS\Query\Exception;

use Componenta\CQRS\Exception\ExceptionInterface;
use InvalidArgumentException;
use Throwable;

/** A name resolver does not support the given query. */
final class UnsupportedQueryException extends InvalidArgumentException implements ExceptionInterface
{
    public function __construct(
        public readonly object $query,
        ?string $message = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            $message ?? sprintf('No name resolver supports query "%s".', $query::class),
            previous: $previous,
        );
    }
}
