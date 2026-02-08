<?php

declare(strict_types=1);

namespace JobTracker\Tests;

use JobTracker\ActivityLog;
use JobTracker\ApplicationInput;
use JobTracker\ApplicationRepository;
use JobTracker\Database;
use JobTracker\Http\HttpException;
use PHPUnit\Framework\TestCase;

final class ActivityLogTest extends TestCase
{
    public function testAddsNotesNewestFirst(): void
    {
        $pdo = Database::connect('sqlite::memory:');
        $id = (int) (new ApplicationRepository($pdo))->create(new ApplicationInput('Acme', 'Dev'))['id'];
        $day = 1;
        $log = new ActivityLog($pdo, function () use (&$day) {
            return new \DateTimeImmutable("2026-10-0{$day}T10:00:00+03:00");
        });

        $log->add($id, 'Отправил резюме');
        $day = 3;
        $log->add($id, ' Позвонила HR ');

        self::assertSame(['Позвонила HR', 'Отправил резюме'], array_column($log->forApplication($id), 'text'));
        $this->expectException(HttpException::class);
        $log->add($id, '   ');
    }
}
