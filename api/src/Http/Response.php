<?php

declare(strict_types=1);

namespace JobTracker\Http;

final readonly class Response
{
    /** @param array<string, string> $headers */
    public function __construct(public int $status, public string $body = '', public array $headers = [])
    {
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self($status, json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), ['Content-Type' => 'application/json; charset=utf-8']);
    }

    public static function noContent(): self
    {
        return new self(204);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }
        echo $this->body;
    }
}
