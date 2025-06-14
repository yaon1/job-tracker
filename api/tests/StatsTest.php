<?php

declare(strict_types=1);

namespace JobTracker\Tests;

use JobTracker\ApplicationInput;
use JobTracker\ApplicationRepository;
use JobTracker\Database;
use JobTracker\Stats;
use JobTracker\Status;
use PHPUnit\Framework\TestCase;

final class StatsTest extends TestCase
{
    public function testFunnelUsesHistory(): void
    {
        $pdo = Database::connect('sqlite::memory:');
        $repo = new ApplicationRepository($pdo);
        $make = fn (Status ...$path) => array_reduce(
            $path,
            fn (int $id, Status $s) => (int) $repo->moveTo($id, $s)['id'],
            (int) $repo->create(new ApplicationInput('C', 'P'))['id'],
        );

        $make(Status::Applied);
        $make(Status::Applied, Status::Rejected);
        $make(Status::Applied, Status::Interview, Status::Rejected);
        $make(Status::Applied, Status::Interview, Status::Offer);
        $make(); // wishlist only

        $stats = (new Stats($pdo))->summary();

        self::assertSame(['wishlist' => 1, 'applied' => 1, 'interview' => 0, 'offer' => 1, 'rejected' => 2], $stats['byStatus']);
        self::assertSame(['applied' => 4, 'interview' => 2, 'offer' => 1], $stats['funnel']);
        self::assertSame(50.0, $stats['responseRate']);
    }

    public function testStatsBySourceAndResponseTime(): void
    {
        $pdo = Database::connect('sqlite::memory:');
        $day = 0;
        $repo = new ApplicationRepository($pdo, function () use (&$day) {
            return (new \DateTimeImmutable('2026-10-01T00:00:00Z'))->modify("+$day days");
        });

        $a = (int) $repo->create(new ApplicationInput('A', 'Dev', status: Status::Applied, source: 'hh', externalId: '1'))['id'];
        $b = (int) $repo->create(new ApplicationInput('B', 'Dev', status: Status::Applied))['id'];
        $day = 4;
        $repo->moveTo($a, Status::Interview);

        $stats = (new Stats($pdo))->summary();
        self::assertSame(['applied' => 1, 'interview' => 1, 'rate' => 100.0], $stats['bySource']['hh']);
        self::assertSame(['applied' => 1, 'interview' => 0, 'rate' => 0.0], $stats['bySource']['manual']);
        self::assertSame(4.0, $stats['medianDaysToResponse']);
        unset($b);
    }
}
