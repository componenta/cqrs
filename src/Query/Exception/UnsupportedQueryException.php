<?php

declare(strict_types=1);

namespace Componenta\CQRS\Query\Exception;

use Componenta\CQRS\Exception\ExceptionInterface;
use InvalidArgumentException;

/** A name resolver does not support the given query. */
final class UnsupportedQueryException extends InvalidArgumentException implements ExceptionInterface
{
}
