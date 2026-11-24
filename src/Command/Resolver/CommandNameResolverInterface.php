<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Resolver;

use Componenta\CQRS\Exception\ExceptionInterface;

/** Resolves the logical name used to look up a command. */
interface CommandNameResolverInterface
{
    /**
     * Returns whether this resolver can determine the name of the given command.
     *
     * False is a normal result, not an error. This does not check handler
     * registration or availability. Support is evaluated for the message
     * instance, not merely its class; callers may probe again before resolving.
     *
     * @throws \Throwable If checking support itself fails.
     */
    public function supports(object $command): bool;

    /**
     * Resolves the command name without looking up or invoking a handler.
     *
     * The returned string is used as the lookup key. It is not a promise that
     * a handler exists. A resolver must reject a message it does not support.
     *
     * @throws ExceptionInterface If the command is not supported.
     * @throws \Throwable If name resolution fails for another reason.
     */
    public function resolve(object $command): string;
}
