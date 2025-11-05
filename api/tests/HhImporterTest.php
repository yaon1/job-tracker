<?php

declare(strict_types=1);

namespace JobTracker\Tests;

use JobTracker\HhImporter;
use JobTracker\Http\HttpException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HhImporterTest extends TestCase
{
    /** @return iterable<array{string, ?string}> */
    public static function urls(): iterable
    {
        yield ['https://hh.ru/vacancy/123456', '123456'];
        yield ['https://spb.hh.ru/vacancy/42?from=search', '42'];
        yield ['https://hh.kz/vacancy/7', '7'];
        yield ['https://evilhh.ru/vacancy/1', null];
        yield ['https://hh.ru/employer/1', null];
    }

    #[DataProvider('urls')]
    public function testExtractsVacancyId(string $url, ?string $id): void
    {
        self::assertSame($id, HhImporter::vacancyId($url));
    }

    public function testMapsVacancy(): void
    {
        $requested = null;
        $importer = new HhImporter(function (string $url) use (&$requested): string {
            $requested = $url;

            return (string) json_encode([
                'name' => 'PHP-разработчик',
                'employer' => ['name' => 'Рога и копыта'],
                'salary' => ['from' => 250000, 'to' => null, 'currency' => 'RUR'],
            ]);
        });

        $input = $importer->import('https://spb.hh.ru/vacancy/42?from=search');

        self::assertSame('https://api.hh.ru/vacancies/42', $requested);
        self::assertSame('Рога и копыта', $input->company);
        self::assertSame(250000, $input->salaryFrom);
        self::assertNull($input->salaryTo);
        self::assertSame(['hh', '42', 'https://hh.ru/vacancy/42'], [$input->source, $input->externalId, $input->url]);
    }

    public function testUpstreamFailureIs502(): void
    {
        $importer = new HhImporter(fn(string $url): string => throw new \RuntimeException('down'));

        $this->expectExceptionCode(502);
        $importer->import('https://hh.ru/vacancy/1');
    }

    public function testRejectsForeignLinks(): void
    {
        $this->expectException(HttpException::class);
        (new HhImporter(fn(string $u): string => ''))->import('https://example.com');
    }
}
