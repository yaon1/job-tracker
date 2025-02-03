<?php

declare(strict_types=1);

namespace JobTracker\Http;

final class Router
{
    /** @var list<array{string, string, \Closure(Request): Response}> */
    private array $routes = [];

    /** @param \Closure(Request): Response $handler path may contain {name} placeholders */
    public function add(string $method, string $path, \Closure $handler): self
    {
        $regex = '~^' . preg_replace('~\{(\w+)\}~', '(?<$1>[^/]+)', $path) . '$~';
        $this->routes[] = [$method, $regex, $handler];

        return $this;
    }

    public function dispatch(Request $request): Response
    {
        try {
            $allowed = [];
            foreach ($this->routes as [$method, $regex, $handler]) {
                if (preg_match($regex, $request->path, $m) !== 1) {
                    continue;
                }
                if ($method !== $request->method) {
                    $allowed[] = $method;
                    continue;
                }

                return $handler($request->withParams(array_filter($m, is_string(...), ARRAY_FILTER_USE_KEY)));
            }

            return $allowed === []
                ? Response::json(['error' => 'Not found'], 404)
                : new Response(405, '', ['Allow' => implode(', ', $allowed)]);
        } catch (HttpException $e) {
            return Response::json(array_filter(['error' => $e->getMessage(), 'fields' => $e->errors]), $e->getCode());
        }
    }
}
