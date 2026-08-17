<?php

declare(strict_types=1);

namespace JobTracker\Tests;

use JobTracker\Http\HttpException;
use JobTracker\Http\Request;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    public function testHeadersAreCaseInsensitive(): void
    {
        $request = new Request('GET', '/', headers: ['authorization' => 'Bearer x']);
        self::assertSame('Bearer x', $request->header('Authorization'));
        self::assertSame('', $request->header('X-Missing'));
    }

    public function testParamsKeepHeaders(): void
    {
        $request = (new Request('GET', '/api/applications/5', headers: ['accept' => 'text/csv']))->withParams(['id' => '5']);
        self::assertSame([5, 'text/csv'], [$request->intParam('id'), $request->header('accept')]);
    }

    public function testRejectsNonObjectJson(): void
    {
        $this->expectException(HttpException::class);
        (new Request('POST', '/', body: '[1,2]'))->json();
    }
}
