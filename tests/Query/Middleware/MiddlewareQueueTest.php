<?php

declare(strict_types=1);

use Componenta\CQRS\Query\Context\Context;
use Componenta\CQRS\Query\Context\ContextInterface;
use Componenta\CQRS\Query\Middleware\MiddlewareInterface;
use Componenta\CQRS\Query\Middleware\MiddlewareQueue;

it('builds an onion pipeline from a middleware list', function (): void {
    $observed = new ArrayObject();
    $makeMiddleware = static function (string $name) use ($observed): MiddlewareInterface {
        return new readonly class($name, $observed) implements MiddlewareInterface {
            public function __construct(
                private string $name,
                private ArrayObject $observed,
            ) {
            }

            public function handle(
                object $query,
                ContextInterface $context,
                callable $next,
            ): mixed {
                $this->observed[] = $this->name . ':before';
                $result = $next($query, $context);
                $this->observed[] = $this->name . ':after';

                return $result;
            }
        };
    };
    $queue = MiddlewareQueue::from([
        $makeMiddleware('first'),
        $makeMiddleware('second'),
    ]);

    $result = $queue->handle(
        new stdClass(),
        new Context(),
        static function (object $query, ContextInterface $context) use ($observed): string {
            $observed[] = 'terminal';

            return 'done';
        },
    );

    expect($result)->toBe('done')
        ->and($observed->getArrayCopy())->toBe([
            'first:before',
            'second:before',
            'terminal',
            'second:after',
            'first:after',
        ]);
});
