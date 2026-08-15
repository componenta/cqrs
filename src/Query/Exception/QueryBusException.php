<?php

declare(strict_types=1);

namespace Componenta\CQRS\Query\Exception;

use Componenta\CQRS\Command\OperationInterface;
use RuntimeException;
use Throwable;

/** Exception thrown when query execution fails. */
final class QueryBusException extends RuntimeException
{
    public function __construct(
        public readonly object $query,
        public readonly OperationInterface $operation,
        string $message = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }

    public static function fromThrowable(
        object $query,
        OperationInterface $operation,
        Throwable $throwable,
    ): self
    {
        return new self(
            query: $query,
            operation: $operation,
            message: $throwable->getMessage(),
            previous: $throwable,
        );
    }
}
