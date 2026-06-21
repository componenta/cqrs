<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Exception;

use RuntimeException;

/**
 * Thrown when handler is not found for command.
 */
final class HandlerNotFoundException extends RuntimeException implements LocatorExceptionInterface
{
    public function __construct(
        public readonly string $commandName,
        ?string $message = null
    ) {
        parent::__construct($message ?? "Handler not found for command '$commandName'");
    }
}