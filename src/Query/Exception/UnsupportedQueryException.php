<?php

declare(strict_types=1);

namespace Componenta\CQRS\Query\Exception;

use InvalidArgumentException;

/** A name resolver does not support the given query. */
final class UnsupportedQueryException extends InvalidArgumentException
{
}
