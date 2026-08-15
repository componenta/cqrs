<?php

declare(strict_types=1);

namespace Componenta\CQRS\Command\Exception;

use RuntimeException;
use Throwable;

final class CommandFailureNotificationException extends RuntimeException
{
    public function __construct(
        public readonly Throwable $commandFailure,
        public readonly Throwable $notificationFailure,
    ) {
        parent::__construct(
            sprintf(
                'Command failed with "%s" and its failure notification also failed with "%s".',
                $commandFailure->getMessage(),
                $notificationFailure->getMessage(),
            ),
            previous: $commandFailure,
        );
    }
}
