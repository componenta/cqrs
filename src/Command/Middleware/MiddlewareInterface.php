<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Middleware;

use Componenta\CQRS\Command\OperationInterface;
use Throwable;

/** Intercepts command operation processing. */
interface MiddlewareInterface
{
    /**
     * Processes the operation around the next pipeline stage.
     *
     * May delegate with the original or a derived operation, return without
     * delegating, or explicitly retry or handle failures. Return the resulting
     * operation, not the bare command handler result. There is no implicit
     * transaction or retry guarantee.
     *
     * @throws Throwable If middleware or downstream processing fails and the failure is not handled.
     */
    public function execute(
        OperationInterface $operation,
        OperationHandlerInterface $handler,
    ): OperationInterface;
}
