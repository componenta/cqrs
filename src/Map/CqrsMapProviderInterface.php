<?php

declare(strict_types=1);

namespace Componenta\CQRS\Map;

interface CqrsMapProviderInterface
{
    public function map(): CqrsMap;
}
