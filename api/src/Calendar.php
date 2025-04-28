<?php

declare(strict_types=1);

namespace JobTracker;

/** iCalendar (RFC 5545) feed of interviews, subscribable from Google/Apple/Outlook calendars. */
final class Calendar
{
    private const KIND_LABELS = ['hr' => 'HR-интервью', 'tech' => 'Техническое интервью', 'homework' => 'Тестовое задание', 'final' => 'Финальное интервью', 'other' => 'Встреча'];

    /** @param list<array<string, scalar|null>> $interviews rows from InterviewRepository::upcoming() */
    public static function render(array $interviews, string $host = 'job-tracker'): string
    {
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//job-tracker//interviews//RU', 'CALSCALE:GREGORIAN', 'X-WR-CALNAME:Собеседования'];
        foreach ($interviews as $i) {
            $start = new \DateTimeImmutable((string) $i['scheduled_at']);
            $end = $start->modify('+' . (int) $i['duration_min'] . ' minutes');
            $kind = self::KIND_LABELS[(string) $i['kind']] ?? 'Встреча';
            $event = [
                'BEGIN:VEVENT',
                'UID:interview-' . $i['id'] . '@' . $host,
                'DTSTAMP:' . gmdate('Ymd\THis\Z'),
                'DTSTART:' . $start->format('Ymd\THis\Z'),
                'DTEND:' . $end->format('Ymd\THis\Z'),
                'SUMMARY:' . self::escape("$kind: {$i['company']} — {$i['position']}"),
            ];
            if ((string) $i['location'] !== '') {
                $event[] = 'LOCATION:' . self::escape((string) $i['location']);
            }
            if ((string) $i['notes'] !== '') {
                $event[] = 'DESCRIPTION:' . self::escape((string) $i['notes']);
            }
            // A reminder 30 minutes before, so the calendar app pings you.
            array_push($event, 'BEGIN:VALARM', 'TRIGGER:-PT30M', 'ACTION:DISPLAY', 'DESCRIPTION:' . self::escape("Через 30 минут: {$i['company']}"), 'END:VALARM', 'END:VEVENT');
            array_push($lines, ...$event);
        }
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(self::fold(...), $lines)) . "\r\n";
    }

    private static function escape(string $text): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\\,', '\\n', '\\n'], $text);
    }

    /** Lines longer than 75 octets are folded with CRLF + space, without splitting UTF-8 characters. */
    private static function fold(string $line): string
    {
        $out = '';
        $current = '';
        foreach (mb_str_split($line) as $char) {
            if (strlen($current . $char) > 75) {
                $out .= $current . "\r\n ";
                $current = '';
            }
            $current .= $char;
        }

        return $out . $current;
    }
}
