<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Exception;

use Componenta\CQRS\Exception\ExceptionInterface;

/** Marks CQRS-owned command handler and listener locator errors, not container failures. */
interface LocatorExceptionInterface extends ExceptionInterface
{
}
