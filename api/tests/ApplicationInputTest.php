<?php

declare(strict_types=1);

namespace JobTracker\Tests;

use JobTracker\ApplicationInput;
use JobTracker\Http\HttpException;
use JobTracker\Status;
use PHPUnit\Framework\TestCase;

final class ApplicationInputTest extends TestCase
{
    public function testParsesValidInput(): void
    {
        $input = ApplicationInput::fromArray([
            'company' => ' Acme ',
            'position' => 'PHP Developer',
            'salary_from' => 200000,
            'currency' => 'rur',
            'status' => 'applied',
            'next_action_at' => '2026-10-25',
        ]);

        self::assertSame('Acme', $input->company);
        self::assertSame('RUR', $input->currency);
        self::assertSame(Status::Applied, $input->status);
        self::assertSame('2026-10-25', $input->nextActionAt?->format('Y-m-d'));
    }

    public function testCollectsAllFieldErrors(): void
    {
        try {
            ApplicationInput::fromArray(['position' => 'x', 'url' => 'nope', 'salary_from' => 5, 'salary_to' => 1, 'status' => 'hired']);
            self::fail('Expected validation error');
        } catch (HttpException $e) {
            self::assertSame(422, $e->getCode());
            self::assertSame(['company', 'url', 'salary_to', 'status'], array_keys($e->errors));
        }
    }
}
