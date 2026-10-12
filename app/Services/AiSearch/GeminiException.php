<?php

namespace App\Services\AiSearch;

use RuntimeException;
use Throwable;

class GeminiException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $status, $previous);
    }

    /**
     * Crédit prépayé épuisé : inutile de réessayer avant rechargement du compte.
     */
    public function isOutOfCredit(): bool
    {
        return $this->status === 402;
    }
}
