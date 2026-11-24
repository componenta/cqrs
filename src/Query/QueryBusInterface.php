<?php

declare(strict_types=1);

namespace Componenta\CQRS\Query;

use Componenta\CQRS\Exception\ExceptionInterface;
use Componenta\CQRS\Query\Context\ContextInterface;
use Throwable;

/** Handles queries through the configured processing pipeline. */
interface QueryBusInterface
{
    /**
     * Returns the result of the query pipeline, including null when appropriate.
     *
     * An array context is converted to the default Context. A supplied
     * ContextInterface is passed to middleware unchanged. Middleware may return
     * a result without invoking the terminal handler.
     *
     * Exceptions escaping the pipeline propagate without being wrapped by the bus.
     *
     * @param ContextInterface|array<string, mixed> $context Per-dispatch middleware attributes.
     * @throws ExceptionInterface If CQRS cannot process the query, for example because
     *                            the input is invalid, a handler is missing, or its registration is invalid.
     * @throws Throwable If any other failure escapes the processing pipeline.
     */
    public function handle(object $query, ContextInterface|array $context = []): mixed;
}
