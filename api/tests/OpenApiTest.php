<?php

declare(strict_types=1);

namespace JobTracker\Tests;

use PHPUnit\Framework\TestCase;

/** Keeps openapi.json in sync with the router: every route documented, nothing stale. */
final class OpenApiTest extends TestCase
{
    public function testEveryRouteIsDocumented(): void
    {
        preg_match_all("/->add\\('(\\w+)', '([^']+)'/", (string) file_get_contents(__DIR__ . '/../src/App.php'), $m, PREG_SET_ORDER);
        $routes = array_map(static fn (array $r) => strtolower($r[1]) . ' ' . $r[2], $m);

        $spec = json_decode((string) file_get_contents(__DIR__ . '/../openapi.json'), true);
        self::assertIsArray($spec);
        self::assertIsArray($spec['paths']);
        $documented = [];
        foreach ($spec['paths'] as $path => $ops) {
            self::assertIsArray($ops);
            foreach (array_keys($ops) as $method) {
                $documented[] = "$method $path";
            }
        }

        sort($routes);
        sort($documented);
        self::assertSame($routes, $documented);
    }
}
