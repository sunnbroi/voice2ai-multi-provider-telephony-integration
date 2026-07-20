<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Исключение транспортного/протокольного уровня для провайдеров телефонии.
 * Используется адаптерами (например, PhonetService) для сигнализации об ошибках HTTP,
 * неверном формате ответа и исчерпании ретраев.
 */
final class ProviderHttpException extends RuntimeException
{
    public function __construct(
        string $message = '',
        public readonly ?int $status = null,
        public readonly ?string $contentType = null,
        public readonly ?string $bodyPreview = null,
        ?\Throwable $previous = null,
        int $code = 0
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function getContentType(): ?string
    {
        return $this->contentType;
    }

    public function getBodyPreview(): ?string
    {
        return $this->bodyPreview;
    }
}
