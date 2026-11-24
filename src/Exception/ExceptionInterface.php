<?php

declare(strict_types=1);

namespace Componenta\CQRS\Exception;

/** Marks failures owned by the CQRS package, not exceptions of its dependencies. */
interface ExceptionInterface extends \Throwable
{
}
