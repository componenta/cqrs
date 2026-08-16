<?php

declare(strict_types=1);

namespace Componenta\CQRS\Map\Exception;

use RuntimeException;

final class MissingCqrsMapException extends RuntimeException
{
    public static function forEnvironment(string $environment): self
    {
        return new self(sprintf(
            'The compiled CQRS map is missing in "%s" environment. Clear stale caches and run app:build.',
            $environment,
        ));
    }

    public static function forProduction(): self
    {
        return self::forEnvironment('production');
    }
}
