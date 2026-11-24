<?php

declare(strict_types=1);

namespace Componenta\CQRS\Internal;

use Closure;
use Componenta\DI\CallableInvokerInterface;

/**
 * Adapts a located native callable to the DI 5 invocation contract, where
 * string specifications have container-service precedence.
 *
 * @internal
 */
final class LocatedHandlerInvoker
{
    /**
     * @param callable $handler The already selected message handler.
     */
    public static function invoke(CallableInvokerInterface $invoker, mixed $handler, object $message): mixed
    {
        // Preserve native target identity without changing non-string callables.
        // Static method visibility remains in the configured invoker's scope.
        if (is_string($handler)) {
            $handler = str_contains($handler, '::')
                ? explode('::', $handler, 2)
                : Closure::fromCallable($handler);
        }

        return $invoker->call($handler, [$message]);
    }
}
