<?php

declare(strict_types=1);

namespace JobTracker;

final readonly class Stats
{
    public function __construct(private \PDO $pdo)
    {
    }

    /**
     * Counts per status plus a funnel: how many applications reached each stage,
     * using status history so rejected-after-interview still counts as interviewed.
     *
     * @return array{byStatus: array<string, int>, funnel: array{applied: int, interview: int, offer: int}, responseRate: float|null}
     */
    public function summary(): array
    {
        $byStatus = array_fill_keys(array_column(Status::cases(), 'value'), 0);
        foreach ($this->rows('SELECT status, COUNT(*) AS n FROM applications GROUP BY status') as $r) {
            if (is_string($r['status']) && is_int($r['n'])) {
                $byStatus[$r['status']] = $r['n'];
            }
        }

        $reached = [1 => 0, 2 => 0, 3 => 0];
        $maxRank = [];
        foreach ($this->rows('SELECT application_id, status FROM status_changes') as $r) {
            $status = is_string($r['status']) ? Status::tryFrom($r['status']) : null;
            if ($status !== null && is_int($r['application_id'])) {
                $maxRank[$r['application_id']] = max($maxRank[$r['application_id']] ?? 0, $status->rank());
            }
        }
        foreach ($maxRank as $rank) {
            for ($i = 1; $i <= $rank; $i++) {
                $reached[$i]++;
            }
        }

        return [
            'byStatus' => $byStatus,
            'funnel' => ['applied' => $reached[1], 'interview' => $reached[2], 'offer' => $reached[3]],
            'responseRate' => $reached[1] === 0 ? null : round($reached[2] / $reached[1] * 100, 1),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function rows(string $sql): array
    {
        $stmt = $this->pdo->query($sql);
        $rows = [];
        foreach ($stmt === false ? [] : $stmt->fetchAll() as $row) {
            if (is_array($row)) {
                $rows[] = array_combine(array_map('strval', array_keys($row)), $row);
            }
        }

        return $rows;
    }
}
