<?php

namespace App\Core;

use RuntimeException;

// Carries an HTTP status plus optional field errors; always safe to show the client.
class AppException extends RuntimeException
{
    private int $status;
    private array $errors;

    public function __construct(string $message, int $status = 400, array $errors = [])
    {
        parent::__construct($message);
        $this->status = $status;
        $this->errors = $errors;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
