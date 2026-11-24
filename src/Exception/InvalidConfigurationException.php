<?php

declare(strict_types=1);

namespace Componenta\CQRS\Exception;

/** Invalid CQRS component configuration or dependency wiring. */
final class InvalidConfigurationException extends \LogicException implements ExceptionInterface
{
}
