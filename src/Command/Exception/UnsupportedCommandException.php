<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Exception;

use Componenta\CQRS\Exception\ExceptionInterface;
use InvalidArgumentException;
use Throwable;

/** A name resolver does not support the given command. */
final class UnsupportedCommandException extends InvalidArgumentException implements ExceptionInterface
{
    public function __construct(
        public readonly object $command,
        ?string $message = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            $message ?? sprintf('No name resolver supports command "%s".', $command::class),
            previous: $previous,
        );
    }
}
