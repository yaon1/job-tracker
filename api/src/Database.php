<?php

declare(strict_types=1);

namespace JobTracker;

final class Database
{
    private const MIGRATIONS = [
        <<<'SQL'
        CREATE TABLE applications (
            id INTEGER PRIMARY KEY,
            company TEXT NOT NULL,
            position TEXT NOT NULL,
            url TEXT,
            salary_from INTEGER,
            salary_to INTEGER,
            currency TEXT,
            status TEXT NOT NULL,
            notes TEXT NOT NULL DEFAULT '',
            next_action_at TEXT,
            source TEXT NOT NULL DEFAULT 'manual',
            external_id TEXT,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )
        SQL,
        <<<'SQL'
        CREATE TABLE status_changes (
            application_id INTEGER NOT NULL REFERENCES applications(id) ON DELETE CASCADE,
            status TEXT NOT NULL,
            changed_at TEXT NOT NULL
        )
        SQL,
        'CREATE UNIQUE INDEX applications_external ON applications (source, external_id) WHERE external_id IS NOT NULL',
        <<<'SQL'
        CREATE TABLE contacts (
            id INTEGER PRIMARY KEY,
            application_id INTEGER NOT NULL REFERENCES applications(id) ON DELETE CASCADE,
            name TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT '',
            email TEXT,
            phone TEXT,
            telegram TEXT
        )
        SQL,
        <<<'SQL'
        CREATE TABLE interviews (
            id INTEGER PRIMARY KEY,
            application_id INTEGER NOT NULL REFERENCES applications(id) ON DELETE CASCADE,
            scheduled_at TEXT NOT NULL,
            duration_min INTEGER NOT NULL DEFAULT 60,
            kind TEXT NOT NULL,
            location TEXT NOT NULL DEFAULT '',
            notes TEXT NOT NULL DEFAULT ''
        )
        SQL,
        <<<'SQL'
        CREATE TABLE application_tags (
            application_id INTEGER NOT NULL REFERENCES applications(id) ON DELETE CASCADE,
            tag TEXT NOT NULL,
            PRIMARY KEY (application_id, tag)
        )
        SQL,
    ];

    public static function connect(string $dsn): \PDO
    {
        $pdo = new \PDO($dsn, options: [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        self::migrate($pdo);

        return $pdo;
    }

    private static function migrate(\PDO $pdo): void
    {
        $stmt = $pdo->query('PRAGMA user_version');
        $version = $stmt === false ? null : $stmt->fetchColumn();
        if (!is_int($version)) {
            throw new \RuntimeException('Cannot read schema version');
        }
        foreach (array_slice(self::MIGRATIONS, $version, preserve_keys: true) as $i => $sql) {
            $pdo->beginTransaction();
            $pdo->exec($sql);
            $pdo->exec('PRAGMA user_version = ' . ($i + 1));
            $pdo->commit();
        }
    }
}
