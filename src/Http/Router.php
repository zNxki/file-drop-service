<?php
declare(strict_types=1);

namespace FileDrop\Http;

use FileDrop\Exception\HttpException;

final class Router
{
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void
    {
        $regex = '~^' . preg_replace('/\{(\w+)\}/', '(?P<$1>[A-Za-z0-9]{3,32})', $pattern) . '$~';
        $this->routes[] = [strtoupper($method), $regex, $handler];
    }

    public function dispatch(Request $request): Response
    {
        $allowed = [];
        foreach ($this->routes as [$method, $regex, $handler]) {
            if (!preg_match($regex, $request->path, $m)) {
                continue;
            }
            if ($method !== $request->method) {
                $allowed[] = $method;
                continue;
            }
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            return $handler($request, $params);
        }

        if ($allowed) {
            throw new HttpException(405, 'Metodo non consentito.');
        }
        throw new HttpException(404, 'Pagina non trovata.');
    }
}