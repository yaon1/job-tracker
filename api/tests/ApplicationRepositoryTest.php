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
            fn() => new \DateTimeImmutable('2026-10-01T00:00:00+00:00')->modify('+' . $this->tick++ . ' minutes'),
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

    public function testFiltersBySourceAndSalaryAndSorts(): void
    {
        $this->repo->create(new ApplicationInput('Low', 'Dev', salaryFrom: 100_000, salaryTo: 150_000));
        $this->repo->create(new ApplicationInput('High', 'Dev', salaryFrom: 300_000, source: 'hh', externalId: '1'));
        $this->repo->create(new ApplicationInput('Unknown', 'Dev'));

        self::assertSame(['High'], array_column($this->repo->list(filter: new \JobTracker\ListFilter(minSalary: 200_000)), 'company'));
        self::assertSame(['High'], array_column($this->repo->list(filter: new \JobTracker\ListFilter(source: 'hh')), 'company'));
        self::assertSame(['High', 'Low', 'Unknown'], array_column($this->repo->list(filter: new \JobTracker\ListFilter(sort: 'salary')), 'company'));
        self::assertSame(['Low'], array_column($this->repo->list(filter: new \JobTracker\ListFilter(sort: 'salary', limit: 1, offset: 1)), 'company'));
    }

    public function testTagsAreNormalisedAndFilterable(): void
    {
        $a = (int) $this->repo->create(new ApplicationInput('Acme', 'Dev'))['id'];
        $this->repo->create(new ApplicationInput('Other', 'Dev'));

        self::assertSame(['remote', 'startup'], $this->repo->setTags($a, ['Startup', ' remote', 'REMOTE', '']));
        self::assertSame(['Acme'], array_column($this->repo->list(filter: new \JobTracker\ListFilter(tag: 'remote')), 'company'));
        self::assertSame('remote,startup', $this->repo->list(filter: new \JobTracker\ListFilter(tag: 'remote'))[0]['tags']);
        self::assertSame(['remote', 'startup'], $this->repo->allTags());
    }

    public function testDueByListsOpenApplicationsWithPastOrTodayActions(): void
    {
        $d = static fn(string $s) => new \DateTimeImmutable($s);
        $this->repo->create(new ApplicationInput('Overdue', 'Dev', status: \JobTracker\Status::Applied, nextActionAt: $d('2026-10-01')));
        $this->repo->create(new ApplicationInput('Today', 'Dev', nextActionAt: $d('2026-10-20')));
        $this->repo->create(new ApplicationInput('Later', 'Dev', nextActionAt: $d('2026-11-01')));
        $this->repo->create(new ApplicationInput('Closed', 'Dev', status: \JobTracker\Status::Rejected, nextActionAt: $d('2026-10-01')));

        self::assertSame(['Overdue', 'Today'], array_column($this->repo->dueBy($d('2026-10-20')), 'company'));
    }
}
