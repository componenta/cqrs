<?php

declare(strict_types=1);

namespace Componenta\CQRS\Handler;

/**
 * Marker contract for compiled handlers whose complete runtime argument list
 * is the dispatched message itself.
 */
interface DirectHandlerCallableInterface
{
    public function __invoke(object $message): mixed;
}
