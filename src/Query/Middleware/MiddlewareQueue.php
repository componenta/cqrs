<?php

declare(strict_types=1);

namespace Componenta\CQRS\Query\Middleware;

use Componenta\CQRS\Query\Context\ContextInterface;

final readonly class MiddlewareQueue implements MiddlewareInterface
{
    /** @var list<MiddlewareInterface> */
    private array $middlewares;

    public function __construct(MiddlewareInterface ...$middlewares)
    {
        $this->middlewares = array_values($middlewares);
    }

    public function handle(object $query, ContextInterface $context, callable $next): mixed
    {
        for ($index = count($this->middlewares) - 1; $index >= 0; --$index) {
            $middleware = $this->middlewares[$index];
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
