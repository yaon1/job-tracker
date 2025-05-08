<?php

declare(strict_types=1);

namespace JobTracker;

use JobTracker\Http\HttpException;

/** Query options for the application list, parsed from the query string. */
final readonly class ListFilter
{
    public const SORTS = ['updated', 'created', 'salary', 'company', 'next_action'];

    public function __construct(
        public ?Status $status = null,
        public string $query = '',
        public ?string $source = null,
        public ?int $minSalary = null,
        public ?string $tag = null,
        public string $sort = 'updated',
        public int $limit = 200,
        public int $offset = 0,
    ) {
    }

    /** @param array<string, string> $query */
    public static function fromQuery(array $query): self
    {
        $status = isset($query['status']) ? Status::tryFrom($query['status']) : null;
        if (isset($query['status']) && $status === null) {
            throw HttpException::validation(['status' => 'Неизвестный статус']);
        }
        $sort = $query['sort'] ?? 'updated';
        if (!in_array($sort, self::SORTS, true)) {
            throw HttpException::validation(['sort' => 'Сортировка: ' . implode(', ', self::SORTS)]);
        }
        $int = static fn (string $key, int $default, int $min, int $max): int => isset($query[$key]) && ctype_digit($query[$key])
            ? max($min, min($max, (int) $query[$key]))
            : $default;

        return new self(
            $status,
            trim($query['q'] ?? ''),
            isset($query['source']) && $query['source'] !== '' ? $query['source'] : null,
            isset($query['min_salary']) && ctype_digit($query['min_salary']) ? (int) $query['min_salary'] : null,
            isset($query['tag']) && $query['tag'] !== '' ? mb_strtolower($query['tag']) : null,
            $sort,
            $int('limit', 200, 1, 500),
            $int('offset', 0, 0, PHP_INT_MAX),
        );
    }
}
