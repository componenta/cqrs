<?php

declare(strict_types=1);

use Componenta\CQRS\Benchmarks\CqrsBench;

require_once dirname(__DIR__) . '/benchmarks/CqrsBench.php';

it('keeps benchmark setup compatible with the current CQRS APIs', function (): void {
    $benchmark = new CqrsBench();
    $benchmark->setUp();

    expect($benchmark)->toBeInstanceOf(CqrsBench::class);
});
