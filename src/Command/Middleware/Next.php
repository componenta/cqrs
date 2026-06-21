<?php

namespace Componenta\CQRS\Command\Middleware;

use Componenta\CQRS\Command\OperationInterface;

final readonly class Next implements OperationHandlerInterface
{
    /**
     * @param int $index
     * @param MiddlewareInterface[] $middlewares
     */
    public function __construct(
        private int $index,
        private array $middlewares,
        private OperationHandlerInterface $handler
    ) {
    }

    public function handle(OperationInterface $operation): OperationInterface
    {
        return ($this->middlewares[$this->index] ?? null)?->execute($operation,
            new Next( $this->index + 1, $this->middlewares, $this->handler)
        ) ??  $this->handler->handle($operation);
    }
}