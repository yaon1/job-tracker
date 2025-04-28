<?php

declare(strict_types=1);

namespace JobTracker\Tests;

use JobTracker\ApplicationInput;
use JobTracker\ApplicationRepository;
use JobTracker\Database;
use JobTracker\Http\HttpException;
use JobTracker\InterviewRepository;
use PHPUnit\Framework\TestCase;

final class InterviewRepositoryTest extends TestCase
{
    public function testSchedulesInUtcAndListsUpcoming(): void
    {
        $pdo = Database::connect('sqlite::memory:');
        $appId = (int) (new ApplicationRepository($pdo))->create(new ApplicationInput('Acme', 'PHP Dev'))['id'];
        $interviews = new InterviewRepository($pdo);

        $tech = $interviews->schedule($appId, ['scheduled_at' => '2026-11-05T15:00:00+03:00', 'kind' => 'tech', 'location' => 'https://meet.example/abc']);
        $interviews->schedule($appId, ['scheduled_at' => '2026-11-01T10:00:00+00:00', 'kind' => 'hr']);

        self::assertSame('2026-11-05T12:00:00Z', $tech['scheduled_at']);
        self::assertSame('Acme', $tech['company']);
        $upcoming = $interviews->upcoming(new \DateTimeImmutable('2026-11-02T00:00:00Z'));
        self::assertSame(['tech'], array_column($upcoming, 'kind'));
    }

    public function testRejectsBadInput(): void
    {
        $this->expectException(HttpException::class);
        (new InterviewRepository(Database::connect('sqlite::memory:')))->schedule(1, ['scheduled_at' => 'tomorrow', 'kind' => 'party']);
    }
}
