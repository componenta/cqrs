<?php

declare(strict_types=1);

namespace Componenta\CQRS\Query\Resolver;

interface QueryNameResolverInterface
{
    public function supports(object $query): bool;

    public function resolve(object $query): string;
}
