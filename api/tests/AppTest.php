<?php

declare(strict_types=1);

namespace JobTracker\Tests;

use JobTracker\App;
use JobTracker\Database;
use JobTracker\HhImporter;
use JobTracker\Http\Request;
use JobTracker\Http\Router;
use PHPUnit\Framework\TestCase;

final class AppTest extends TestCase
{
    private Router $app;

    protected function setUp(): void
    {
        $hh = new HhImporter(fn(string $u): string => '{"name":"Dev","employer":{"name":"Acme"},"salary":null}');
        $this->app = App::routes(Database::connect('sqlite::memory:'), $hh);
    }

    /** @return array{int, mixed} */
    private function call(string $method, string $path, mixed $body = null): array
    {
        $response = $this->app->dispatch(new Request($method, $path, body: $body === null ? '' : (string) json_encode($body)));

        return [$response->status, json_decode($response->body ?: 'null', true)];
    }

    public function testApplicationLifecycle(): void
    {
        [$status, $created] = $this->call('POST', '/api/applications', ['company' => 'Acme', 'position' => 'Dev']);
        self::assertSame(201, $status);
        self::assertIsArray($created);
        $id = $created['id'];
        self::assertIsInt($id);

        self::assertSame(200, $this->call('PATCH', "/api/applications/$id/status", ['status' => 'interview'])[0]);
        self::assertSame(422, $this->call('PATCH', "/api/applications/$id/status", ['status' => 'hired'])[0]);
        [, $history] = $this->call('GET', "/api/applications/$id/history");
        self::assertIsArray($history);
        self::assertSame(['wishlist', 'interview'], array_column($history, 'status'));

        self::assertSame(204, $this->call('DELETE', "/api/applications/$id")[0]);
        self::assertSame(404, $this->call('GET', "/api/applications/$id")[0]);
    }

    public function testHhImportRejectsDuplicates(): void
    {
        self::assertSame(201, $this->call('POST', '/api/import/hh', ['url' => 'https://hh.ru/vacancy/9'])[0]);
        [$status, $body] = $this->call('POST', '/api/import/hh', ['url' => 'https://hh.ru/vacancy/9']);
        self::assertSame(409, $status);
        self::assertIsArray($body);
        self::assertSame(1, $body['id']);
    }

    public function testRejectsInvalidJson(): void
    {
        $response = $this->app->dispatch(new Request('POST', '/api/applications', body: '{oops'));
        self::assertSame(400, $response->status);
    }

    public function testContactsEndpoints(): void
    {
        [, $app] = $this->call('POST', '/api/applications', ['company' => 'Acme', 'position' => 'Dev']);
        self::assertIsArray($app);
        $id = $app['id'];
        self::assertIsInt($id);

        [$status, $contact] = $this->call('POST', "/api/applications/$id/contacts", ['name' => 'Олег', 'role' => 'Tech lead']);
        self::assertSame(201, $status);
        self::assertIsArray($contact);
        self::assertSame(404, $this->call('POST', '/api/applications/999/contacts', ['name' => 'X'])[0]);
        [, $list] = $this->call('GET', "/api/applications/$id/contacts");
        self::assertIsArray($list);
        self::assertCount(1, $list);
        $contactId = $contact['id'];
        self::assertIsInt($contactId);
        self::assertSame(204, $this->call('DELETE', "/api/applications/$id/contacts/$contactId")[0]);
    }

    public function testInterviewsAndCalendarFeed(): void
    {
        [, $app] = $this->call('POST', '/api/applications', ['company' => 'Acme', 'position' => 'Dev']);
        self::assertIsArray($app);
        $id = $app['id'];
        self::assertIsInt($id);
        $when = (new \DateTimeImmutable('+3 days'))->format(DATE_ATOM);

        self::assertSame(201, $this->call('POST', "/api/applications/$id/interviews", ['scheduled_at' => $when, 'kind' => 'tech'])[0]);
        self::assertSame(422, $this->call('POST', "/api/applications/$id/interviews", ['scheduled_at' => 'soon'])[0]);
        [, $list] = $this->call('GET', '/api/interviews');
        self::assertIsArray($list);
        self::assertCount(1, $list);

        $ics = $this->app->dispatch(new Request('GET', '/api/interviews.ics'));
        self::assertSame('text/calendar; charset=utf-8', $ics->headers['Content-Type']);
        self::assertStringContainsString('SUMMARY:Техническое интервью: Acme — Dev', $ics->body);
    }

    public function testTagEndpoints(): void
    {
        [, $app] = $this->call('POST', '/api/applications', ['company' => 'Acme', 'position' => 'Dev']);
        self::assertIsArray($app);
        $id = $app['id'];
        self::assertIsInt($id);

        self::assertSame([200, ['remote']], $this->call('PUT', "/api/applications/$id/tags", ['tags' => ['Remote']]));
        self::assertSame(422, $this->call('PUT', "/api/applications/$id/tags", ['tags' => [1]])[0]);
        self::assertSame([200, ['remote']], $this->call('GET', '/api/tags'));
    }

    public function testCsvRoundTripThroughTheApi(): void
    {
        $response = $this->app->dispatch(new Request('POST', '/api/import.csv', body: "company,position\nAcme,Dev\nBeta,QA\n"));
        self::assertSame([201, '{"imported":2}'], [$response->status, $response->body]);

        $csv = $this->app->dispatch(new Request('GET', '/api/export.csv'));
        self::assertSame('text/csv; charset=utf-8', $csv->headers['Content-Type']);
        self::assertStringContainsString('Beta,QA,wishlist', $csv->body);
    }

    public function testWarnsAboutDuplicatesUnlessForced(): void
    {
        $this->call('POST', '/api/applications', ['company' => 'Acme', 'position' => 'PHP Dev']);
        [$status, $body] = $this->call('POST', '/api/applications', ['company' => 'ACME', 'position' => 'php dev']);
        self::assertSame(409, $status);
        self::assertIsArray($body);
        self::assertSame(1, $body['id']);

        $forced = $this->app->dispatch(new Request('POST', '/api/applications', ['force' => '1'], (string) json_encode(['company' => 'Acme', 'position' => 'PHP Dev'])));
        self::assertSame(201, $forced->status);
    }
}
