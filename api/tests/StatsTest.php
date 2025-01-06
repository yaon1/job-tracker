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
}
