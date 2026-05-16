<?php

namespace App\Modules\Orders\Exceptions;

use RuntimeException;

class OrderCheckoutException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $errors
     */
    public function __construct(
        string $message,
        private readonly array $errors,
        private readonly int $status,
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
