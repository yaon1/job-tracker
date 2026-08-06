<?php

declare(strict_types=1);

namespace JobTracker\Tests;

use JobTracker\Status;
use PHPUnit\Framework\TestCase;

final class StatusTest extends TestCase
{
    public function testEveryStatusHasALabelAndFunnelRank(): void
    {
        foreach (Status::cases() as $status) {
            self::assertNotSame('', $status->label());
        }
        // A rejection still counts as having applied; an offer is the furthest stage.
        self::assertSame(Status::Applied->rank(), Status::Rejected->rank());
        self::assertGreaterThan(Status::Interview->rank(), Status::Offer->rank());
    }
}
