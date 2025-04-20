<?php

declare(strict_types=1);

namespace JobTracker\Tests;

use JobTracker\ApplicationInput;
use JobTracker\ApplicationRepository;
use JobTracker\ContactRepository;
use JobTracker\Database;
use JobTracker\Http\HttpException;
use PHPUnit\Framework\TestCase;

final class ContactRepositoryTest extends TestCase
{
    public function testAddsListsAndDeletesContacts(): void
    {
        $pdo = Database::connect('sqlite::memory:');
        $appId = (int) (new ApplicationRepository($pdo))->create(new ApplicationInput('Acme', 'Dev'))['id'];
        $contacts = new ContactRepository($pdo);

        $anna = $contacts->add($appId, ['name' => 'Анна', 'role' => 'HR', 'telegram' => '@anna_hr', 'email' => 'anna@acme.dev']);
        self::assertSame('anna_hr', $anna['telegram']);
        self::assertSame(['Анна'], array_column($contacts->forApplication($appId), 'name'));

        $contacts->delete($appId, (int) $anna['id']);
        self::assertSame([], $contacts->forApplication($appId));
    }

    public function testValidatesFields(): void
    {
        $pdo = Database::connect('sqlite::memory:');
        try {
            (new ContactRepository($pdo))->add(1, ['name' => '', 'email' => 'nope', 'telegram' => 'a b']);
            self::fail('Expected validation error');
        } catch (HttpException $e) {
            self::assertSame(['name', 'email', 'telegram'], array_keys($e->errors));
        }
    }
}
