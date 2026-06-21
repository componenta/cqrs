<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Exception;

use LogicException;

/**
 * Thrown when handler definition is invalid.
 */
final class InvalidHandlerException extends LogicException implements LocatorExceptionInterface
{
    public static function missingInvoke(string $class): self
    {
        return new self("Handler '$class' must have __invoke() method");
    }

    public static function missingParameter(string $handler): self
    {
        return new self("Handler '$handler' must have command parameter");
    }

    public static function invalidParameterType(string $handler): self
    {
        return new self("Handler '$handler' first parameter must be typed with command class");
    }
}