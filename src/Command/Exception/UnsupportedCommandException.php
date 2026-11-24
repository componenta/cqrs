<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Exception;

use Componenta\CQRS\Exception\ExceptionInterface;
use InvalidArgumentException;

/** A name resolver does not support the given command. */
final class UnsupportedCommandException extends InvalidArgumentException implements ExceptionInterface
{
}
