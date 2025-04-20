<?php

declare(strict_types=1);

namespace JobTracker;

use JobTracker\Http\HttpException;

/** Recruiters and interviewers attached to an application. */
final readonly class ContactRepository
{
    public function __construct(private \PDO $pdo)
    {
    }

    /**
     * @param array<mixed> $data
     * @return array<string, scalar|null>
     */
    public function add(int $applicationId, array $data): array
    {
        $name = is_string($data['name'] ?? null) ? trim($data['name']) : '';
        $email = is_string($data['email'] ?? null) && $data['email'] !== '' ? trim($data['email']) : null;
        $telegram = is_string($data['telegram'] ?? null) && $data['telegram'] !== '' ? ltrim(trim($data['telegram']), '@') : null;
        $errors = [];
        if ($name === '' || mb_strlen($name) > 100) {
            $errors['name'] = 'Имя от 1 до 100 символов';
        }
        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Некорректный email';
        }
        if ($telegram !== null && preg_match('/^\w{5,32}$/', $telegram) !== 1) {
            $errors['telegram'] = 'Username Telegram: 5–32 латинских букв, цифр или _';
        }
        if ($errors !== []) {
            throw HttpException::validation($errors);
        }

        $stmt = $this->pdo->prepare('INSERT INTO contacts (application_id, name, role, email, phone, telegram) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $applicationId,
            $name,
            is_string($data['role'] ?? null) ? trim($data['role']) : '',
            $email,
            is_string($data['phone'] ?? null) && $data['phone'] !== '' ? trim($data['phone']) : null,
            $telegram,
        ]);

        return $this->find((int) $this->pdo->lastInsertId()) ?? throw new \LogicException('Inserted contact not found');
    }

    /** @return list<array<string, scalar|null>> */
    public function forApplication(int $applicationId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM contacts WHERE application_id = ? ORDER BY id');
        $stmt->execute([$applicationId]);

        return array_values(array_map(self::row(...), array_filter($stmt->fetchAll(), is_array(...))));
    }

    public function delete(int $applicationId, int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM contacts WHERE id = ? AND application_id = ?');
        $stmt->execute([$id, $applicationId]);
        if ($stmt->rowCount() === 0) {
            throw HttpException::notFound('Contact');
        }
    }

    /** @return array<string, scalar|null>|null */
    private function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM contacts WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return is_array($row) ? self::row($row) : null;
    }

    /**
     * @param array<mixed> $row
     * @return array<string, scalar|null>
     */
    private static function row(array $row): array
    {
        $out = [];
        foreach ($row as $k => $v) {
            $out[(string) $k] = is_scalar($v) ? $v : null;
        }

        return $out;
    }
}
