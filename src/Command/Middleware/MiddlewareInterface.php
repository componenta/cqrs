<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Middleware;

use Componenta\CQRS\Command\OperationInterface;

interface MiddlewareInterface
{
    public function execute(
        OperationInterface $operation,
        OperationHandlerInterface $handler,
    ): OperationInterface;
}
