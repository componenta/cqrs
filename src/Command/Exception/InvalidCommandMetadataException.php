<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Exception;

use Componenta\CQRS\Exception\ExceptionInterface;

final class InvalidCommandMetadataException extends \InvalidArgumentException implements ExceptionInterface
{
}
