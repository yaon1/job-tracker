<?php

declare(strict_types=1);

namespace JobTracker;

use JobTracker\Http\HttpException;

/** Turns an hh.ru vacancy link into application fields via the public api.hh.ru. */
final readonly class HhImporter
{
    /** @param \Closure(string): string $fetch returns the response body for a URL */
    public function __construct(private \Closure $fetch)
    {
    }

    public static function vacancyId(string $url): ?string
    {
        return preg_match('~^https?://(?:[\w-]+\.)?hh\.(?:ru|kz|uz)/vacancy/(\d+)~', $url, $m) === 1 ? $m[1] : null;
    }

    public function import(string $url): ApplicationInput
    {
        $id = self::vacancyId($url) ?? throw HttpException::validation(['url' => 'Нужна ссылка вида https://hh.ru/vacancy/123']);

        try {
            $data = json_decode(($this->fetch)("https://api.hh.ru/vacancies/$id"), true, 64, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw new HttpException(502, 'hh.ru не ответил или вакансия не найдена');
        }
        if (!is_array($data) || !is_string($data['name'] ?? null)) {
            throw new HttpException(502, 'Неожиданный ответ hh.ru');
        }

        $employer = is_array($data['employer'] ?? null) && is_string($data['employer']['name'] ?? null) ? $data['employer']['name'] : 'Неизвестная компания';
        $salary = is_array($data['salary'] ?? null) ? $data['salary'] : [];
        $int = static fn (mixed $v): ?int => is_int($v) ? $v : null;

        return new ApplicationInput(
            company: $employer,
            position: $data['name'],
            url: "https://hh.ru/vacancy/$id",
            salaryFrom: $int($salary['from'] ?? null),
            salaryTo: $int($salary['to'] ?? null),
            currency: is_string($salary['currency'] ?? null) ? $salary['currency'] : null,
            source: 'hh',
            externalId: $id,
        );
    }

    /** @param string|null $accessToken hh.ru application token; anonymous API access is rejected with 403 */
    public static function httpFetcher(?string $accessToken = null): \Closure
    {
        return static function (string $url) use ($accessToken): string {
            // hh.ru requires a descriptive User-Agent for API clients.
            $headers = "User-Agent: job-tracker/0.1 (https://github.com/yaon1/job-tracker)\r\n";
            if ($accessToken !== null && $accessToken !== '') {
                $headers .= "Authorization: Bearer $accessToken\r\n";
            }
            $context = stream_context_create(['http' => ['timeout' => 5, 'header' => $headers]]);
            $body = @file_get_contents($url, false, $context);

            return $body === false ? throw new \RuntimeException("GET $url failed") : $body;
        };
    }
}
