<?php

declare(strict_types=1);

namespace JobTracker;

use JobTracker\Http\HttpException;

/**
 * @phpstan-type Row array<string, scalar|null>
 */
final readonly class ApplicationRepository
{
    /** @param (\Closure(): \DateTimeImmutable)|null $clock */
    public function __construct(private \PDO $pdo, private ?\Closure $clock = null)
    {
    }

    private function now(): string
    {
        return ($this->clock === null ? new \DateTimeImmutable() : ($this->clock)())->format(DATE_ATOM);
    }

    /** @return Row */
    public function create(ApplicationInput $in): array
    {
        $now = $this->now();
        $this->pdo->beginTransaction();
        $this->statement(
            'INSERT INTO applications (company, position, url, salary_from, salary_to, currency, status, notes, next_action_at, source, external_id, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [...$this->fields($in), $in->source, $in->externalId, $now, $now],
        );
        $id = (int) $this->pdo->lastInsertId();
        $this->logStatus($id, $in->status, $now);
        $this->pdo->commit();

        return $this->get($id);
    }

    /** @return Row */
    public function update(int $id, ApplicationInput $in): array
    {
        $current = $this->get($id);
        $now = $this->now();
        $this->pdo->beginTransaction();
        $this->statement(
            'UPDATE applications SET company = ?, position = ?, url = ?, salary_from = ?, salary_to = ?, currency = ?, status = ?,
             notes = ?, next_action_at = ?, updated_at = ? WHERE id = ?',
            [...$this->fields($in), $now, $id],
        );
        if ($current['status'] !== $in->status->value) {
            $this->logStatus($id, $in->status, $now);
        }
        $this->pdo->commit();

        return $this->get($id);
    }

    /** @return Row */
    public function moveTo(int $id, Status $status): array
    {
        $current = $this->get($id);
        if ($current['status'] === $status->value) {
            return $current;
        }
        $now = $this->now();
        $this->pdo->beginTransaction();
        $this->statement('UPDATE applications SET status = ?, updated_at = ? WHERE id = ?', [$status->value, $now, $id]);
        $this->logStatus($id, $status, $now);
        $this->pdo->commit();

        return $this->get($id);
    }

    /** @return Row */
    public function get(int $id): array
    {
        $row = $this->statement('SELECT * FROM applications WHERE id = ?', [$id])->fetch();

        return is_array($row) ? $this->row($row) : throw HttpException::notFound('Application');
    }

    /** @return list<Row> */
    public function list(?Status $status = null, string $query = '', ?ListFilter $filter = null): array
    {
        $filter ??= new ListFilter($status, $query);
        // Tags come back as one comma-separated column to avoid a query per row.
        $sql = "SELECT a.*, (SELECT GROUP_CONCAT(tag, ',') FROM (SELECT tag FROM application_tags t WHERE t.application_id = a.id ORDER BY tag)) AS tags FROM applications a WHERE 1 = 1";
        $params = [];
        if ($filter->status !== null) {
            $sql .= ' AND status = ?';
            $params[] = $filter->status->value;
        }
        if ($filter->query !== '') {
            $sql .= " AND (company LIKE ? ESCAPE '\\' OR position LIKE ? ESCAPE '\\')";
            $like = '%' . addcslashes($filter->query, '%_\\') . '%';
            array_push($params, $like, $like);
        }
        if ($filter->tag !== null) {
            $sql .= ' AND EXISTS (SELECT 1 FROM application_tags t WHERE t.application_id = a.id AND t.tag = ?)';
            $params[] = $filter->tag;
        }
        if ($filter->source !== null) {
            $sql .= ' AND source = ?';
            $params[] = $filter->source;
        }
        if ($filter->minSalary !== null) {
            // A vacancy qualifies if its range can reach the wanted salary.
            $sql .= ' AND COALESCE(salary_to, salary_from) >= ?';
            $params[] = $filter->minSalary;
        }
        $sql .= ' ORDER BY ' . match ($filter->sort) {
            'created' => 'created_at DESC',
            'salary' => 'COALESCE(salary_to, salary_from) IS NULL, COALESCE(salary_to, salary_from) DESC',
            'company' => 'company COLLATE NOCASE',
            'next_action' => 'next_action_at IS NULL, next_action_at',
            default => 'updated_at DESC',
        } . ', id DESC LIMIT ? OFFSET ?';
        array_push($params, $filter->limit, $filter->offset);

        return array_values(array_map($this->row(...), array_filter($this->statement($sql, $params)->fetchAll(), is_array(...))));
    }

    /**
     * Replaces the tags of an application. Tags are lower-cased and trimmed: "Remote" and "remote " are one tag.
     *
     * @param list<string> $tags
     * @return list<string>
     */
    public function setTags(int $id, array $tags): array
    {
        $this->get($id);
        $clean = array_values(array_unique(array_filter(array_map(static fn (string $t) => mb_strtolower(trim($t)), $tags), static fn (string $t) => $t !== '' && mb_strlen($t) <= 30)));
        $this->pdo->beginTransaction();
        $this->statement('DELETE FROM application_tags WHERE application_id = ?', [$id]);
        foreach ($clean as $tag) {
            $this->statement('INSERT INTO application_tags (application_id, tag) VALUES (?, ?)', [$id, $tag]);
        }
        $this->pdo->commit();
        sort($clean);

        return $clean;
    }

    /**
     * Open applications whose next step is due by $date (overdue first), skipping finished ones.
     *
     * @return list<array<string, scalar|null>>
     */
    public function dueBy(\DateTimeImmutable $date): array
    {
        $rows = $this->statement(
            "SELECT * FROM applications WHERE next_action_at IS NOT NULL AND next_action_at <= ? AND status NOT IN ('offer', 'rejected') ORDER BY next_action_at, id",
            [$date->format('Y-m-d')],
        )->fetchAll();

        return array_values(array_map($this->row(...), array_filter($rows, is_array(...))));
    }

    /** @return list<string> every tag in use, for autocomplete */
    public function allTags(): array
    {
        return array_values(array_filter($this->statement('SELECT DISTINCT tag FROM application_tags ORDER BY tag', [])->fetchAll(\PDO::FETCH_COLUMN), is_string(...)));
    }

    public function delete(int $id): void
    {
        if ($this->statement('DELETE FROM applications WHERE id = ?', [$id])->rowCount() === 0) {
            throw HttpException::notFound('Application');
        }
    }

    public function findExternal(string $source, string $externalId): ?int
    {
        $id = $this->statement('SELECT id FROM applications WHERE source = ? AND external_id = ?', [$source, $externalId])->fetchColumn();

        return is_int($id) ? $id : null;
    }

    /** @return list<array{status: string, changed_at: string}> */
    public function history(int $id): array
    {
        $this->get($id);
        $rows = $this->statement('SELECT status, changed_at FROM status_changes WHERE application_id = ? ORDER BY changed_at, rowid', [$id])->fetchAll();

        $history = [];
        foreach ($rows as $r) {
            if (is_array($r) && is_string($r['status'] ?? null) && is_string($r['changed_at'] ?? null)) {
                $history[] = ['status' => $r['status'], 'changed_at' => $r['changed_at']];
            }
        }

        return $history;
    }

    /** @return list<scalar|null> */
    private function fields(ApplicationInput $in): array
    {
        return [
            $in->company, $in->position, $in->url, $in->salaryFrom, $in->salaryTo, $in->currency,
            $in->status->value, $in->notes, $in->nextActionAt?->format('Y-m-d'),
        ];
    }

    private function logStatus(int $id, Status $status, string $at): void
    {
        $this->statement('INSERT INTO status_changes (application_id, status, changed_at) VALUES (?, ?, ?)', [$id, $status->value, $at]);
    }

    /** @param list<scalar|null> $params */
    private function statement(string $sql, array $params): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        // execute($params) would bind everything as text; SQLite then compares numbers
        // with strings in expressions without column affinity (e.g. COALESCE) and fails.
        foreach ($params as $i => $value) {
            $stmt->bindValue($i + 1, $value, match (true) {
                is_int($value) => \PDO::PARAM_INT,
                $value === null => \PDO::PARAM_NULL,
                is_bool($value) => \PDO::PARAM_BOOL,
                default => \PDO::PARAM_STR,
            });
        }
        $stmt->execute();

        return $stmt;
    }

    /**
     * @param array<mixed> $row
     * @return Row
     */
    private function row(array $row): array
    {
        $out = [];
        foreach ($row as $k => $v) {
            $out[(string) $k] = is_scalar($v) ? $v : null;
        }

        return $out;
    }
}
