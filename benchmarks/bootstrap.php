<?php

declare(strict_types=1);

$loader = require dirname(__DIR__) . '/vendor/autoload.php';
$source = getenv('CQRS_BENCH_SOURCE');
if ($source !== false && $source !== '') {
    if (!is_dir($source)) {
        throw new RuntimeException('Benchmark source directory does not exist: ' . $source);
    }

    $loader->setPsr4('Componenta\\CQRS\\', $source);
}
