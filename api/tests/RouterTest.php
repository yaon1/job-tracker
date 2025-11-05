<?php

declare(strict_types=1);

namespace JobTracker\Tests;

use JobTracker\Http\HttpException;
use JobTracker\Http\Request;
use JobTracker\Http\Response;
use JobTracker\Http\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = (new Router())
            ->add('GET', '/items/{id}', fn(Request $r) => Response::json(['id' => $r->intParam('id')]))
            ->add('POST', '/items', fn(Request $r) => throw HttpException::validation(['name' => 'required']));
    }

    public function testMatchesParams(): void
    {
        self::assertSame('{"id":5}', $this->router->dispatch(new Request('GET', '/items/5'))->body);
    }

    public function testNotFoundAndMethodNotAllowed(): void
    {
        self::assertSame(404, $this->router->dispatch(new Request('GET', '/nope'))->status);
        self::assertSame(404, $this->router->dispatch(new Request('GET', '/items/abc'))->status);
        $response = $this->router->dispatch(new Request('DELETE', '/items'));
        self::assertSame([405, 'POST'], [$response->status, $response->headers['Allow']]);
    }

    public function testHttpExceptionsBecomeJson(): void
    {
        $response = $this->router->dispatch(new Request('POST', '/items'));
        self::assertSame(422, $response->status);
        self::assertSame('{"error":"Validation failed","fields":{"name":"required"}}', $response->body);
    }
}
