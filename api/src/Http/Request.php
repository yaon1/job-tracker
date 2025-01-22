<?php

declare(strict_types=1);

namespace JobTracker\Http;

final readonly class Request
{
    /**
     * @param array<string, string> $query
     * @param array<string, string> $params route parameters
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $query = [],
        public string $body = '',
        public array $params = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $uri = is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : '/';
        $method = is_string($_SERVER['REQUEST_METHOD'] ?? null) ? $_SERVER['REQUEST_METHOD'] : 'GET';

        $query = [];
        foreach ($_GET as $key => $value) {
            if (is_string($value)) {
                $query[(string) $key] = $value;
            }
        }

        return new self(
            strtoupper($method),
            rtrim((string) parse_url($uri, PHP_URL_PATH), '/') ?: '/',
            $query,
            (string) file_get_contents('php://input'),
        );
    }

    /** @param array<string, string> $params */
    public function withParams(array $params): self
    {
        return new self($this->method, $this->path, $this->query, $this->body, $params);
    }

    /** @return array<mixed> */
    public function json(): array
    {
        try {
            $data = json_decode($this->body === '' ? '{}' : $this->body, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new HttpException(400, 'Тело запроса должно быть JSON');
        }

        return is_array($data) ? $data : throw new HttpException(400, 'Ожидается JSON-объект');
    }

    public function intParam(string $name): int
    {
        $v = $this->params[$name] ?? '';

        return ctype_digit($v) ? (int) $v : throw HttpException::notFound();
    }
}
