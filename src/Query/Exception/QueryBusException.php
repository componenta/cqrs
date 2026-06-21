<?php

declare(strict_types=1);

namespace Componenta\CQRS\Query\Exception;

use Componenta\CQRS\Command\OperationInterface;
use Componenta\CQRS\QueryInterface;
use RuntimeException;
use Throwable;

/**
 * Exception thrown when query execution fails.
 *
 * Contains the original query and operation for debugging and logging.
 *
 * @example
 * ```php
 * try {
 *     $queryBus->dispatch(new GetUserByIdQuery(123));
 * } catch (QueryBusException $e) {
 *     $logger->error('Query failed', [
 *         'query' => $e->query::class,
 *         'operation_id' => (string)$e->operation->uuid,
 *         'error' => $e->getMessage(),
 *     ]);
 * }
 * ```
 */
final class QueryBusException extends RuntimeException
{
    /**
     * Creates a new query bus exception.
     *
     * @param QueryInterface $query The query that failed
     * @param OperationInterface $operation The operation tracking the execution
     * @param string $message Error message
     * @param Throwable|null $previous Previous exception
     */
    public function __construct(
        public readonly QueryInterface $query,
        public readonly OperationInterface $operation,
        string $message = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Creates exception from a caught throwable.
     *
     * @param QueryInterface $query The query that failed
     * @param OperationInterface $operation The operation tracking the execution
     * @param Throwable $throwable The caught exception
     * @return self
     */
    public static function fromThrowable(
        QueryInterface $query,
        OperationInterface $operation,
        Throwable $throwable,
    ): self {
        return new self(
            query: $query,
            operation: $operation,
            message: $throwable->getMessage(),
            previous: $throwable,
        );
    }
}
