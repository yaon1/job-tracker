<?php

declare(strict_types=1);

namespace JobTracker\Http;

final class HttpException extends \RuntimeException
{
    /** @param array<string, string> $errors field => message */
    public function __construct(int $status, string $message, public readonly array $errors = [])
    {
        parent::__construct($message, $status);
    }

    /** @param array<string, string> $errors */
    public static function validation(array $errors): self
    {
        return new self(422, 'Validation failed', $errors);
    }

    public static function notFound(string $what = 'Resource'): self
    {
        return new self(404, "$what not found");
    }
}
