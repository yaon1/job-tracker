<?php

declare(strict_types=1);

namespace JobTracker;

use JobTracker\Http\HttpException;

/** Validated fields for creating or updating an application. */
final readonly class ApplicationInput
{
    public function __construct(
        public string $company,
        public string $position,
        public ?string $url = null,
        public ?int $salaryFrom = null,
        public ?int $salaryTo = null,
        public ?string $currency = null,
        public Status $status = Status::Wishlist,
        public string $notes = '',
        public ?\DateTimeImmutable $nextActionAt = null,
        public string $source = 'manual',
        public ?string $externalId = null,
    ) {
    }

    /**
     * @param array<mixed> $data
     * @throws HttpException 422 with per-field messages
     */
    public static function fromArray(array $data): self
    {
        $errors = [];
        $string = static function (string $key, bool $required, int $max) use ($data, &$errors): ?string {
            $v = $data[$key] ?? null;
            if ($v === null || $v === '') {
                if ($required) {
                    $errors[$key] = 'Обязательное поле';
                }

                return null;
            }
            if (!is_string($v) || mb_strlen(trim($v)) > $max) {
                $errors[$key] = "Строка до $max символов";

                return null;
            }

            return trim($v);
        };
        $int = static function (string $key) use ($data, &$errors): ?int {
            $v = $data[$key] ?? null;
            if ($v === null || $v === '') {
                return null;
            }
            if (!is_int($v) || $v < 0) {
                $errors[$key] = 'Неотрицательное целое число';

                return null;
            }

            return $v;
        };

        $company = $string('company', true, 200);
        $position = $string('position', true, 200);
        $url = $string('url', false, 2000);
        if ($url !== null && filter_var($url, FILTER_VALIDATE_URL) === false) {
            $errors['url'] = 'Некорректная ссылка';
        }
        $salaryFrom = $int('salary_from');
        $salaryTo = $int('salary_to');
        if ($salaryFrom !== null && $salaryTo !== null && $salaryFrom > $salaryTo) {
            $errors['salary_to'] = 'Должна быть не меньше «от»';
        }
        $currency = $string('currency', false, 3);
        $status = is_string($data['status'] ?? null) ? Status::tryFrom($data['status']) : Status::Wishlist;
        if ($status === null) {
            $errors['status'] = 'Неизвестный статус';
        }
        $next = $string('next_action_at', false, 40);
        $nextAt = $next === null ? null : \DateTimeImmutable::createFromFormat('!Y-m-d', $next);
        if ($nextAt === false) {
            $errors['next_action_at'] = 'Дата в формате ГГГГ-ММ-ДД';
        }

        if ($errors !== [] || $company === null || $position === null || $status === null || $nextAt === false) {
            throw HttpException::validation($errors);
        }

        return new self(
            $company,
            $position,
            $url,
            $salaryFrom,
            $salaryTo,
            $currency === null ? null : strtoupper($currency),
            $status,
            $string('notes', false, 10_000) ?? '',
            $nextAt,
            $string('source', false, 20) ?? 'manual',
            $string('external_id', false, 50),
        );
    }
}
