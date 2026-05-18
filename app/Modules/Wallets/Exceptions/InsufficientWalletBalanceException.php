<?php

namespace App\Modules\Wallets\Exceptions;

use RuntimeException;

class InsufficientWalletBalanceException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $errors
     */
    public function __construct(
        string $message,
        private readonly array $errors,
        private readonly int $status = 409,
    ) {
        parent::__construct($message);
    }

    /**
     * @return array<string, mixed>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function status(): int
    {
        return $this->status;
    }
}
