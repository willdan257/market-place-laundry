<?php

namespace App\Services\AI;

use RuntimeException;
use Throwable;

/**
 * Exception khusus AI Assistant.
 * getMessage() SELALU berisi pesan yang aman ditampilkan ke pelanggan
 * (tanpa detail teknis, API key, atau isi query).
 */
class AIException extends RuntimeException
{
    private int $httpStatus;
    private string $errorCode;
    private ?int $retryAfter;

    public function __construct(
        string $userMessage,
        int $httpStatus = 500,
        string $errorCode = 'ai_error',
        ?int $retryAfter = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($userMessage, 0, $previous);
        $this->httpStatus = $httpStatus;
        $this->errorCode = $errorCode;
        $this->retryAfter = $retryAfter;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function retryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
