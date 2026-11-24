<?php

declare(strict_types=1);

namespace Componenta\CQRS\Query\Resolver;

use Componenta\CQRS\Query\Exception\UnsupportedQueryException;

/** Resolves the logical name used to look up a query. */
interface QueryNameResolverInterface
{
    /**
     * Returns whether this resolver can determine the name of the given query.
     *
     * False is a normal result, not an error. This does not check handler
     * registration or availability. Support is evaluated for the message
     * instance, not merely its class; callers may probe again before resolving.
     *
     * @throws \Throwable If checking support itself fails.
     */
    public function supports(object $query): bool;

    /**
     * Resolves the query name without looking up or invoking a handler.
     *
     * The returned string is used as the lookup key. It is not a promise that
     * a handler exists. A resolver must reject a message it does not support.
     *
     * @throws UnsupportedQueryException If the query is not supported.
     * @throws \Throwable If name resolution fails for another reason.
     */
    public function resolve(object $query): string;
}
