<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Exception;

use LogicException;

/** Thrown when a mapped command handler service cannot satisfy its descriptor. */
final class InvalidHandlerException extends LogicException implements LocatorExceptionInterface
{
    public static function serviceNotInvokable(string $service, string $command): self
    {
        return new self(sprintf(
            'CQRS command handler service "%s" for "%s" is not invokable.',
            $service,
            $command,
        ));
    }

    public static function serviceMethodNotCallable(string $service, string $method): self
    {
        return new self(sprintf(
            'CQRS command handler service "%s" has no public callable method "%s".',
            $service,
            $method,
        ));
    }
}
