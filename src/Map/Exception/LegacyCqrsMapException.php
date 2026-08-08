<?php

declare(strict_types=1);

namespace Componenta\CQRS\Map\Exception;

final class LegacyCqrsMapException extends InvalidCqrsMapException
{
    public static function detected(string $key): self
    {
        return new self(sprintf(
            'Legacy CQRS cache key "%s" is not supported. Clear the application cache and run app:build.',
            $key,
        ));
    }
}
