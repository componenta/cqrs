<?php

namespace Componenta\CQRS\Command\Middleware;

use Componenta\CQRS\Command\OperationInterface;

interface OperationHandlerInterface
{
    public function handle(OperationInterface $operation): OperationInterface ;
}