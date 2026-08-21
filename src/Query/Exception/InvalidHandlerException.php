<?php

declare(strict_types=1);

namespace Componenta\CQRS\Query\Exception;

use LogicException;

/** Thrown when a mapped query handler service cannot satisfy its descriptor. */
final class InvalidHandlerException extends LogicException
{
    public static function serviceNotInvokable(string $service, string $query): self
    {
        return new self(sprintf(
            'CQRS query handler service "%s" for "%s" is not invokable.',
            $service,
            $query,
        ));
    }

    public static function serviceMethodNotCallable(string $service, string $method): self
    {
        return new self(sprintf(
            'CQRS query handler service "%s" has no public callable method "%s".',
            $service,
            $method,
        ));
    }
}
