<?php

declare(strict_types=1);

namespace Componenta\CQRS\Map\Exception;

use Componenta\CQRS\Exception\ExceptionInterface;
use InvalidArgumentException;

class InvalidCqrsMapException extends InvalidArgumentException implements ExceptionInterface
{
}
