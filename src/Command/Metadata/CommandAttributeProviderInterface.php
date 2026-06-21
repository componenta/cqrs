<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Metadata;

use Componenta\CQRS\Command\Attribute\Async;
use Componenta\CQRS\Command\Attribute\Lock;
use Componenta\CQRS\Command\Attribute\Retry;

interface CommandAttributeProviderInterface
{
    public function async(object|string $command): ?Async;

    public function retry(object|string $command): ?Retry;

    public function lock(object|string $command): ?Lock;
}
