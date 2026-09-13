<?php

declare(strict_types=1);

namespace App\Support;

/** Generic HTTP-status-carrying exception, caught by ErrorHandlerMiddleware. */
class ApiException extends \RuntimeException
{
    public function __construct(private readonly int $status, string $message)
    {
        parent::__construct($message);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public static function badRequest(string $message): self
    {
        return new self(400, $message);
    }

    public static function unauthorized(string $message = 'Unauthorized'): self
    {
        return new self(401, $message);
    }

    public static function forbidden(string $message = 'Forbidden'): self
    {
        return new self(403, $message);
    }

    public static function notFound(string $message = 'Not found'): self
    {
        return new self(404, $message);
    }

    public static function conflict(string $message): self
    {
        return new self(409, $message);
    }
}
