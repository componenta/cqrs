<?php

declare(strict_types=1);

namespace Componenta\CQRS\Query;

use Closure;
use Componenta\CQRS\Query\Context\Context;
use Componenta\CQRS\Query\Context\ContextInterface;
use Componenta\CQRS\Query\Middleware\MiddlewareInterface;

final class QueryBus implements QueryBusInterface
{
    private Closure $pipeline;

    public function __construct(
        HandleQuery $handler,
        MiddlewareInterface ... $middlewares
    ) {
        $this->pipeline = self::compile($handler, $middlewares);
    }

    public function handle(object $query, ContextInterface|array $context = []): mixed
    {
        $context = is_array($context) ? new Context($context) : $context;

        return ($this->pipeline)($query, $context);
    }

    /**
     * @param MiddlewareInterface[] $middlewares
     */
    private static function compile(HandleQuery $handler, array $middlewares): Closure
    {
        $pipeline = static fn(object $query, ContextInterface $context): mixed => $handler($query);

        for ($i = \count($middlewares) - 1; $i >= 0; --$i) {
            $middleware = $middlewares[$i];
            $next = $pipeline;

            $pipeline = static fn(object $query, ContextInterface $context): mixed
                => $middleware->handle($query, $context, $next);
        }

        return $pipeline;
    }
}
