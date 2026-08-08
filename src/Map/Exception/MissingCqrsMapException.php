<?php

declare(strict_types=1);

namespace Componenta\CQRS\Map\Exception;

use RuntimeException;

final class MissingCqrsMapException extends RuntimeException
{
    public static function forProduction(): self
    {
        return new self(
            'The compiled CQRS map is missing in production. Clear stale caches and run app:build.',
        );
    }
}
