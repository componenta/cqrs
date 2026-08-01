<?php

declare(strict_types=1);

namespace Componenta\CQRS\Query;

use Closure;
use Componenta\DI\CallableInvokerInterface;

/** Defers the generic DI callable graph until a legacy handler needs it. */
final class LazyCallableInvoker implements CallableInvokerInterface
{
    private ?CallableInvokerInterface $invoker = null;

    /** @param Closure(): CallableInvokerInterface $factory */
    public function __construct(private readonly Closure $factory) {}

    public function call(mixed $callable, array $params = []): mixed
    {
        return ($this->invoker ??= ($this->factory)())->call($callable, $params);
    }
}
