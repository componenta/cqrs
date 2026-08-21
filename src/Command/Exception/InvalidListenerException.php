<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Exception;

use LogicException;

/** Thrown when a mapped command listener service violates the listener contract. */
final class InvalidListenerException extends LogicException implements LocatorExceptionInterface
{
    public static function serviceType(string $service, string $actual): self
    {
        return new self(sprintf(
            'CQRS listener service "%s" must implement CommandListenerInterface; got %s.',
            $service,
            $actual,
        ));
    }
}
