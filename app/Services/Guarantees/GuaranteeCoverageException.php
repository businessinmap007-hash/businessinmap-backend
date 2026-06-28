<?php

namespace App\Services\Guarantees;

use RuntimeException;

final class GuaranteeCoverageException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly array $decision = [],
        int $code = 422,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function decision(): array
    {
        return $this->decision;
    }
}
