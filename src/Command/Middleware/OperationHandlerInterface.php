<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Middleware;

use Componenta\CQRS\Command\OperationInterface;
use Throwable;

/** Processes an operation at one stage of the command pipeline. */
interface OperationHandlerInterface
{
    /**
     * Returns the operation produced by this stage, with a result when
     * completed synchronously. A stage may delegate to another handler.
     *
     * @throws Throwable If processing fails. The terminal HandleCommandHandler
     *                  rejects an operation with an existing result before invocation.
     */
    public function handle(OperationInterface $operation): OperationInterface;
}
