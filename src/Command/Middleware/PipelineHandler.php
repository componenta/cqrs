<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Middleware;

use Componenta\CQRS\Command\OperationInterface;

final readonly class PipelineHandler implements OperationHandlerInterface
{
    public function __construct(
        private MiddlewareInterface $middleware,
        private OperationHandlerInterface $next,
    ) {}

    public function handle(OperationInterface $operation): OperationInterface
    {
        return $this->middleware->execute($operation, $this->next);
    }

    /**
     * @param MiddlewareInterface[] $middlewares
     */
    public static function compile(OperationHandlerInterface $handler, array $middlewares): OperationHandlerInterface
    {
        for ($i = \count($middlewares) - 1; $i >= 0; --$i) {
            $handler = new self($middlewares[$i], $handler);
        }

        return $handler;
    }
}
