<?php

namespace App\Domain\EmployeeOperations\Exceptions;

use RuntimeException;

class EmployeeOperationException extends RuntimeException
{
    public static function duplicate(string $message): self
    {
        return new self($message);
    }
}
