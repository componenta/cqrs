<?php

declare(strict_types=1);

namespace Componenta\CQRS\Handler;

use InvalidArgumentException;

final readonly class DirectHandlerCallable implements DirectHandlerCallableInterface
{
    public function __construct(
        private object $handler,
        private string $method,
    ) {
        if (!is_callable([$handler, $method])) {
            throw new InvalidArgumentException(sprintf(
                'Compiled CQRS handler %s::%s() is not callable.',
                $handler::class,
                $method,
            ));
        }
    }

    public function __invoke(object $message): mixed
    {
        return $this->handler->{$this->method}($message);
    }
}
