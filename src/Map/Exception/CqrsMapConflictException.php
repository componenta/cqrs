<?php

declare(strict_types=1);

namespace Componenta\CQRS\Map\Exception;

final class CqrsMapConflictException extends InvalidCqrsMapException
{
    public static function handler(string $kind, string $message): self
    {
        return new self(sprintf(
            'Conflicting %s handlers are registered for message "%s".',
            $kind,
            $message,
        ));
    }

    public static function metadata(string $command, string $attribute): self
    {
        return new self(sprintf(
            'Conflicting command metadata "%s" is registered for command "%s".',
            $attribute,
            $command,
        ));
    }
}
