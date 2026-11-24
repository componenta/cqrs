<?php

declare(strict_types=1);

namespace Componenta\CQRS\Exception;

/** Invalid input supplied to a CQRS operation or component. */
final class InvalidArgumentException extends \InvalidArgumentException implements ExceptionInterface
{
}
