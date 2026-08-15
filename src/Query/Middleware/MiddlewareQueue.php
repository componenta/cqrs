<?php

declare(strict_types=1);

namespace Componenta\CQRS\Query\Middleware;

use Componenta\CQRS\Query\Context\ContextInterface;
use Componenta\Stdlib\ArrayListReverseIterator;

final class MiddlewareQueue implements MiddlewareInterface
{
    /** @var ArrayListReverseIterator<MiddlewareInterface> */
    private readonly ArrayListReverseIterator $queue;

    public function __construct(
        MiddlewareInterface ...$middlewares
    ) {
        $this->queue = new ArrayListReverseIterator(array_values($middlewares));
    }

    /**
     * @inheritdoc
     */
    public function handle(object $query, ContextInterface $context, callable $next): mixed
    {
        foreach ($this->queue as $middleware) {
            $next = static fn(object $q, ContextInterface $c): mixed
                => $middleware->handle($q, $c, $next);
        }

        return $next($query, $context);
    }

    /** @param list<MiddlewareInterface> $middlewares */
    public static function from(array $middlewares): self
    {
        return new self(...$middlewares);
    }
}