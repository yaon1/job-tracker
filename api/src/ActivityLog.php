<?php

declare(strict_types=1);

namespace JobTracker;

use JobTracker\Http\HttpException;

/** Dated notes per application: "called HR", "sent the test task", "got feedback". */
final readonly class ActivityLog
{
    /** @param (\Closure(): \DateTimeImmutable)|null $clock */
    public function __construct(private \PDO $pdo, private ?\Closure $clock = null)
    {
    }

    /** @return array{id: int, happened_at: string, text: string} */
    public function add(int $applicationId, mixed $text): array
    {
        $text = is_string($text) ? trim($text) : '';
        if ($text === '' || mb_strlen($text) > 2000) {
            throw HttpException::validation(['text' => 'Текст от 1 до 2000 символов']);
        }
        $at = ($this->clock !== null ? ($this->clock)() : new \DateTimeImmutable())->format(DATE_ATOM);
        $stmt = $this->pdo->prepare('INSERT INTO activities (application_id, happened_at, text) VALUES (?, ?, ?)');
        $stmt->execute([$applicationId, $at, $text]);

        return ['id' => (int) $this->pdo->lastInsertId(), 'happened_at' => $at, 'text' => $text];
    }

    /** @return list<array{id: int, happened_at: string, text: string}> newest first */
    public function forApplication(int $applicationId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, happened_at, text FROM activities WHERE application_id = ? ORDER BY happened_at DESC, id DESC');
        $stmt->execute([$applicationId]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            if (is_array($row) && is_int($row['id']) && is_string($row['happened_at']) && is_string($row['text'])) {
                $out[] = ['id' => $row['id'], 'happened_at' => $row['happened_at'], 'text' => $row['text']];
            }
        }

        return $out;
    }
}
