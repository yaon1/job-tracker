<?php

declare(strict_types=1);

namespace JobTracker\Tests;

use JobTracker\ApplicationInput;
use JobTracker\ApplicationRepository;
use JobTracker\Database;
use JobTracker\Http\HttpException;
use JobTracker\Status;
use PHPUnit\Framework\TestCase;

final class ApplicationRepositoryTest extends TestCase
{
    private ApplicationRepository $repo;
    private int $tick = 0;

    protected function setUp(): void
    {
        $this->repo = new ApplicationRepository(
            Database::connect('sqlite::memory:'),
            fn () => new \DateTimeImmutable('2026-10-01T00:00:00+00:00')->modify('+' . $this->tick++ . ' minutes'),
        );
    }

    public function testCreateAndTrackStatusHistory(): void
    {
        $app = $this->repo->create(new ApplicationInput('Acme', 'PHP Dev'));
        $id = (int) $app['id'];

        $this->repo->moveTo($id, Status::Applied);
        $this->repo->moveTo($id, Status::Applied); // no-op, not logged twice
        $this->repo->update($id, new ApplicationInput('Acme', 'Senior PHP Dev', status: Status::Interview));

        self::assertSame('Senior PHP Dev', $this->repo->get($id)['position']);
        self::assertSame(['wishlist', 'applied', 'interview'], array_column($this->repo->history($id), 'status'));
    }

    public function testListFiltersAndSearchesSafely(): void
    {
        $this->repo->create(new ApplicationInput('Acme', 'Backend', status: Status::Applied));
        $this->repo->create(new ApplicationInput('Globex', '100% remote', status: Status::Offer));

        self::assertSame(['Globex', 'Acme'], array_column($this->repo->list(), 'company'));
        self::assertSame(['Acme'], array_column($this->repo->list(Status::Applied), 'company'));
        self::assertSame(['Globex'], array_column($this->repo->list(query: '100%'), 'company'));
        self::assertSame([], $this->repo->list(query: '_'));
    }

    public function testFindsExternalAndDeletes(): void
    {
        $app = $this->repo->create(new ApplicationInput('Acme', 'Dev', source: 'hh', externalId: '123'));
        self::assertSame($app['id'], $this->repo->findExternal('hh', '123'));

        $this->repo->delete((int) $app['id']);
        $this->expectException(HttpException::class);
        $this->repo->get((int) $app['id']);
    }
}
