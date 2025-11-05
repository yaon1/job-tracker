<?php

declare(strict_types=1);

namespace JobTracker\Http;

/**
 * Optional single-user protection: with API_TOKEN set, every /api request needs
 * `Authorization: Bearer <token>`. The calendar feed also accepts `?token=` because
 * calendar apps can't send headers.
 */
final readonly class TokenAuth
{
    public function __construct(private ?string $token) {}

    public function allows(Request $request): bool
    {
        if ($this->token === null || $this->token === '') {
            return true;
        }
        $given = preg_replace('/^Bearer\s+/i', '', $request->header('authorization')) ?? '';
        if ($request->path === '/api/interviews.ics' && $given === '') {
            $given = $request->query['token'] ?? '';
        }

        return hash_equals($this->token, $given);
    }
}
