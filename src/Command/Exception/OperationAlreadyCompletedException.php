<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Exception;

use Componenta\CQRS\Exception\ExceptionInterface;

/** A result cannot be assigned or a handler executed on a completed operation. */
final class OperationAlreadyCompletedException extends \RuntimeException implements ExceptionInterface
{
}
