<?php

declare(strict_types=1);

namespace Ozoto;

final class Router
{
    /** @var array<string, list<array{0: string, 1: array{0: class-string, 1: string}}>> */
    private array $routes = ['GET' => [], 'POST' => []];

    /** @param array{0: class-string, 1: string} $handler */
    public function get(string $pattern, array $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    /** @param array{0: class-string, 1: string} $handler */
    public function post(string $pattern, array $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    /** @param array{0: class-string, 1: string} $handler */
    private function add(string $method, string $pattern, array $handler): void
    {
        $regex = preg_replace('#\\\\\{[a-z]+\\\\\}#', '([A-Za-z0-9_-]+)', preg_quote($pattern, '#'));
        $this->routes[$method][] = ['#^' . $regex . '$#', $handler];
    }

    public function dispatch(string $method, string $path): void
    {
        if ($path !== '/' && str_ends_with($path, '/')) {
            redirect(rtrim($path, '/'), 301);
        }
        if ($method === 'HEAD') {
            $method = 'GET';
        }

        foreach ($this->routes[$method] ?? [] as [$regex, [$class, $action]]) {
            if (preg_match($regex, $path, $matches) === 1) {
                array_shift($matches);
                (new $class())->$action(...$matches);
                return;
            }
        }

        foreach ($this->routes as $other => $routes) {
            foreach ($routes as [$regex]) {
                if ($other !== $method && preg_match($regex, $path) === 1) {
                    header('Allow: ' . $other);
                    abort(405);
                }
            }
        }

        abort(404);
    }
}
