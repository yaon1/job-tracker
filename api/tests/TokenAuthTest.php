<?php

declare(strict_types=1);

namespace JobTracker\Tests;

use JobTracker\Http\Request;
use JobTracker\Http\TokenAuth;
use PHPUnit\Framework\TestCase;

final class TokenAuthTest extends TestCase
{
    public function testAllowsEverythingWithoutAToken(): void
    {
        self::assertTrue((new TokenAuth(null))->allows(new Request('GET', '/api/applications')));
    }

    public function testRequiresBearerToken(): void
    {
        $auth = new TokenAuth('s3cret');

        self::assertFalse($auth->allows(new Request('GET', '/api/applications')));
        self::assertFalse($auth->allows(new Request('GET', '/api/applications', headers: ['authorization' => 'Bearer nope'])));
        self::assertTrue($auth->allows(new Request('GET', '/api/applications', headers: ['authorization' => 'Bearer s3cret'])));
    }

    public function testCalendarFeedAcceptsQueryToken(): void
    {
        $auth = new TokenAuth('s3cret');

        self::assertTrue($auth->allows(new Request('GET', '/api/interviews.ics', ['token' => 's3cret'])));
        self::assertFalse($auth->allows(new Request('GET', '/api/applications', ['token' => 's3cret'])));
    }
}
