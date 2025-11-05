<?php

declare(strict_types=1);

namespace JobTracker;

final readonly class Stats
{
    public function __construct(private \PDO $pdo) {}

    /**
     * Counts per status plus a funnel: how many applications reached each stage,
     * using status history so rejected-after-interview still counts as interviewed.
     *
     * @return array{byStatus: array<string, int>, funnel: array{applied: int, interview: int, offer: int}, responseRate: float|null, bySource: array<string, array{applied: int, interview: int, rate: float|null}>, medianDaysToResponse: float|null}
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
            'bySource' => $this->bySource($maxRank),
            'medianDaysToResponse' => $this->medianDaysToResponse(),
            'byStatus' => $byStatus,
            'funnel' => ['applied' => $reached[1], 'interview' => $reached[2], 'offer' => $reached[3]],
            'responseRate' => $reached[1] === 0 ? null : round($reached[2] / $reached[1] * 100, 1),
        ];
    }

    /**
     * Which channels work: interview rate per source (hh, referral, manual, …).
     *
     * @param array<int, int> $maxRank application id => furthest stage reached
     * @return array<string, array{applied: int, interview: int, rate: float|null}>
     */
    private function bySource(array $maxRank): array
    {
        $result = [];
        foreach ($this->rows('SELECT id, source FROM applications') as $r) {
            if (!is_int($r['id']) || !is_string($r['source'])) {
                continue;
            }
            $rank = $maxRank[$r['id']] ?? 0;
            $result[$r['source']] ??= ['applied' => 0, 'interview' => 0, 'rate' => null];
            $result[$r['source']]['applied'] += $rank >= 1 ? 1 : 0;
            $result[$r['source']]['interview'] += $rank >= 2 ? 1 : 0;
        }
        foreach ($result as &$s) {
            $s['rate'] = $s['applied'] === 0 ? null : round($s['interview'] / $s['applied'] * 100, 1);
        }

        return $result;
    }

    /** Median days from "applied" to the first interview, i.e. how long companies take to answer. */
    private function medianDaysToResponse(): ?float
    {
        $sql = "SELECT a.changed_at AS applied, MIN(i.changed_at) AS answered FROM status_changes a
                JOIN status_changes i ON i.application_id = a.application_id AND i.status IN ('interview', 'offer') AND i.changed_at > a.changed_at
                WHERE a.status = 'applied' GROUP BY a.application_id";
        $days = [];
        foreach ($this->rows($sql) as $r) {
            if (is_string($r['applied']) && is_string($r['answered'])) {
                $days[] = ((new \DateTimeImmutable($r['answered']))->getTimestamp() - (new \DateTimeImmutable($r['applied']))->getTimestamp()) / 86400;
            }
        }
        if ($days === []) {
            return null;
        }
        sort($days);
        $mid = intdiv(count($days), 2);

        return round(count($days) % 2 === 1 ? $days[$mid] : ($days[$mid - 1] + $days[$mid]) / 2, 1);
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
