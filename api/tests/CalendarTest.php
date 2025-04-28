<?php

declare(strict_types=1);

namespace JobTracker\Tests;

use JobTracker\Calendar;
use PHPUnit\Framework\TestCase;

final class CalendarTest extends TestCase
{
    public function testRendersEventsWithAlarmAndEscaping(): void
    {
        $ics = Calendar::render([[
            'id' => 7, 'scheduled_at' => '2026-11-05T12:00:00Z', 'duration_min' => 90, 'kind' => 'tech',
            'company' => 'Acme, Inc', 'position' => 'PHP Dev', 'location' => 'https://meet.example/abc', 'notes' => "Спросить про стек;\nи команду",
        ]]);

        self::assertStringStartsWith("BEGIN:VCALENDAR\r\n", $ics);
        self::assertStringContainsString("DTSTART:20261105T120000Z\r\nDTEND:20261105T133000Z\r\n", $ics);
        self::assertStringContainsString('SUMMARY:Техническое интервью: Acme\\, Inc — PHP Dev', str_replace("\r\n ", '', $ics));
        self::assertStringContainsString('DESCRIPTION:Спросить про стек\;\\nи команду', str_replace("\r\n ", '', $ics));
        self::assertStringContainsString('TRIGGER:-PT30M', $ics);
    }

    public function testFoldsLongLinesAt75Octets(): void
    {
        $ics = Calendar::render([[
            'id' => 1, 'scheduled_at' => '2026-11-05T12:00:00Z', 'duration_min' => 60, 'kind' => 'hr',
            'company' => str_repeat('Очень длинное название ', 5), 'position' => 'Dev', 'location' => '', 'notes' => '',
        ]]);
        foreach (explode("\r\n", $ics) as $line) {
            self::assertLessThanOrEqual(76, strlen($line));
        }
    }
}
