<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command;

use Componenta\CQRS\Exception\ExceptionInterface;
use Throwable;

/** Dispatches commands through the configured processing pipeline. */
interface CommandBusInterface
{
    /**
     * Dispatches one command and returns the resulting operation.
     *
     * Attributes use non-empty string keys (excluding whitespace-only names
     * and numeric names converted by PHP to integer keys). Middleware can
     * complete or defer a command without invoking its terminal handler.
     * A null operation result is valid for deferred processing.
     *
     * Exceptions escaping the pipeline propagate without being wrapped by the bus.
     *
     * @param array<string, mixed> $attributes
     * @throws ExceptionInterface If CQRS cannot process the command, for example because
     *                            the input is invalid, a handler is missing, or its registration is invalid.
     * @throws Throwable If any other failure escapes the processing pipeline.
     */
    public function dispatch(object $command, array $attributes = []): OperationInterface;
}
