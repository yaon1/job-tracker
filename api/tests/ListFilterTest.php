<?php

declare(strict_types=1);

namespace JobTracker\Tests;

use JobTracker\Http\HttpException;
use JobTracker\ListFilter;
use JobTracker\Status;
use PHPUnit\Framework\TestCase;

final class ListFilterTest extends TestCase
{
    public function testParsesAndClampsQuery(): void
    {
        $f = ListFilter::fromQuery(['status' => 'applied', 'q' => ' php ', 'min_salary' => '200000', 'tag' => 'Remote', 'sort' => 'salary', 'limit' => '9999']);

        self::assertSame(Status::Applied, $f->status);
        self::assertSame(['php', 200000, 'remote', 'salary', 500], [$f->query, $f->minSalary, $f->tag, $f->sort, $f->limit]);
    }

    public function testRejectsUnknownStatusAndSort(): void
    {
        $this->expectException(HttpException::class);
        ListFilter::fromQuery(['sort' => 'random']);
    }
}
