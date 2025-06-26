<?php

declare(strict_types=1);

namespace JobTracker\Tests;

use JobTracker\Csv;
use JobTracker\Http\HttpException;
use JobTracker\Status;
use PHPUnit\Framework\TestCase;

final class CsvTest extends TestCase
{
    public function testExportsWithBomAndQuoting(): void
    {
        $csv = Csv::export([['company' => 'Рога, копыта', 'position' => 'PHP', 'status' => 'applied', 'notes' => 'сказали "перезвоним"']]);

        self::assertStringStartsWith("\u{FEFF}company,position,status", $csv);
        self::assertStringContainsString('"Рога, копыта",PHP,applied,,,,,,"сказали ""перезвоним"""', $csv);
    }

    public function testImportsAnyColumnOrderAndRoundTrips(): void
    {
        $inputs = Csv::import("Position,Company,Salary_From,Status,Unused\nPHP Dev,Acme,250000,interview,x\n\n");

        self::assertCount(1, $inputs);
        self::assertSame(['Acme', 'PHP Dev', 250000, Status::Interview], [$inputs[0]->company, $inputs[0]->position, $inputs[0]->salaryFrom, $inputs[0]->status]);
        self::assertCount(1, Csv::import(Csv::export([['company' => 'A', 'position' => 'B', 'status' => 'offer']])));
    }

    public function testReportsTheFailingLine(): void
    {
        try {
            Csv::import("company,position,status\nAcme,Dev,applied\nBeta,Dev,hired\n");
            self::fail('Expected validation error');
        } catch (HttpException $e) {
            self::assertSame(['line 3' => 'status: Неизвестный статус'], $e->errors);
        }
    }
}
