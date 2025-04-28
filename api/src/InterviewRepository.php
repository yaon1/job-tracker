<?php

declare(strict_types=1);

namespace JobTracker;

use JobTracker\Http\HttpException;

final readonly class InterviewRepository
{
    public const KINDS = ['hr', 'tech', 'homework', 'final', 'other'];

    public function __construct(private \PDO $pdo)
    {
    }

    /**
     * @param array<mixed> $data
     * @return array<string, scalar|null>
     */
    public function schedule(int $applicationId, array $data): array
    {
        $errors = [];
        $at = is_string($data['scheduled_at'] ?? null) ? \DateTimeImmutable::createFromFormat(DATE_ATOM, $data['scheduled_at']) : false;
        if ($at === false) {
            $errors['scheduled_at'] = 'Дата и время в формате ISO 8601, например 2026-11-05T15:00:00+03:00';
        }
        $kind = $data['kind'] ?? 'other';
        if (!in_array($kind, self::KINDS, true)) {
            $errors['kind'] = 'Тип: ' . implode(', ', self::KINDS);
        }
        $duration = $data['duration_min'] ?? 60;
        if (!is_int($duration) || $duration < 5 || $duration > 480) {
            $errors['duration_min'] = 'Длительность от 5 до 480 минут';
        }
        if ($errors !== [] || !is_string($kind) || !is_int($duration)) {
            throw HttpException::validation($errors);
        }

        $stmt = $this->pdo->prepare('INSERT INTO interviews (application_id, scheduled_at, duration_min, kind, location, notes) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $applicationId,
            // Stored in UTC so ORDER BY and comparisons work as plain strings.
            $at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            $duration,
            $kind,
            is_string($data['location'] ?? null) ? trim($data['location']) : '',
            is_string($data['notes'] ?? null) ? trim($data['notes']) : '',
        ]);

        return $this->upcoming(new \DateTimeImmutable('@0'), (int) $this->pdo->lastInsertId())[0] ?? throw new \LogicException('Inserted interview not found');
    }

    /**
     * Interviews from $from onwards with company and position, soonest first.
     *
     * @return list<array<string, scalar|null>>
     */
    public function upcoming(\DateTimeImmutable $from, ?int $id = null): array
    {
        $sql = 'SELECT i.*, a.company, a.position FROM interviews i JOIN applications a ON a.id = i.application_id WHERE i.scheduled_at >= ?';
        $params = [$from->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z')];
        if ($id !== null) {
            $sql .= ' AND i.id = ?';
            $params[] = $id;
        }
        $stmt = $this->pdo->prepare($sql . ' ORDER BY i.scheduled_at');
        $stmt->execute($params);

        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            if (is_array($row)) {
                $rows[] = array_map(static fn (mixed $v) => is_scalar($v) ? $v : null, array_combine(array_map('strval', array_keys($row)), $row));
            }
        }

        return $rows;
    }

    public function cancel(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM interviews WHERE id = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            throw HttpException::notFound('Interview');
        }
    }
}
