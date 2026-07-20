<?php

declare(strict_types=1);

namespace App\Services\IntegrationProcess\Exceptions;

use RuntimeException;

final class RecordingDownloadException extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $status = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
