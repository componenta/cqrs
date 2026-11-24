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
        foreach (array_reverse($middlewares) as $middleware) {
            $handler = new self($middleware, $handler);
        }

        return $handler;
    }
}
