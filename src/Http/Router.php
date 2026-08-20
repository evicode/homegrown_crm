<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\Http;

final class Router
{
    /** @var list<Route> */
    private array $routes = [];

    public function __construct(private readonly string $basePath = '')
    {
    }

    /** @param callable(Request, array<string, string>): Response $handler */
    public function add(string $method, string $path, callable $handler, string $name): void
    {
        $this->routes[] = new Route(strtoupper($method), $this->normalize($path), $handler, $name);
    }

    public function dispatch(Request $request): Response
    {
        $path = $this->stripBasePath($request->path);
        $allowed = [];

        foreach ($this->routes as $route) {
            $pattern = $this->compile($route->path);
            if (preg_match('#^' . $pattern . '$#', $path, $matches) !== 1) {
                continue;
            }

            if ($route->method !== $request->method) {
                $allowed[] = $route->method;
                continue;
            }

            $parameters = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            return ($route->handler)($request, $parameters);
        }

        if ($allowed !== []) {
            return Response::json(['error' => 'method_not_allowed'], 405, ['Allow' => implode(', ', array_unique($allowed))]);
        }

        return Response::json(['error' => 'not_found'], 404);
    }

    public function url(string $name, array $parameters = []): string
    {
        foreach ($this->routes as $route) {
            if ($route->name !== $name) {
                continue;
            }
            $path = $route->path;
            foreach ($parameters as $key => $value) {
                $path = str_replace('{' . $key . '}', rawurlencode((string) $value), $path);
            }
            if (preg_match('/\{[^}]+\}/', $path) === 1) {
                throw new \InvalidArgumentException("Missing route parameter for {$name}");
            }
            return ($this->basePath === '' ? '' : $this->basePath) . $path;
        }
        throw new \InvalidArgumentException("Unknown route {$name}");
    }

    private function stripBasePath(string $path): string
    {
        if ($this->basePath !== '' && ($path === $this->basePath || str_starts_with($path, $this->basePath . '/'))) {
            $path = substr($path, strlen($this->basePath)) ?: '/';
        }
        return $this->normalize($path);
    }

    private function normalize(string $path): string
    {
        if ($path === '' || $path === '/') {
            return '/';
        }
        return '/' . trim($path, '/');
    }

    private function compile(string $path): string
    {
        $offset = 0;
        $pattern = '';
        if (preg_match_all('/\{([A-Za-z_][A-Za-z0-9_]*)\}/', $path, $matches, PREG_OFFSET_CAPTURE) === false) {
            return preg_quote($path, '#');
        }
        foreach ($matches[0] as $index => $match) {
            [$token, $position] = $match;
            $pattern .= preg_quote(substr($path, $offset, $position - $offset), '#');
            $pattern .= '(?P<' . $matches[1][$index][0] . '>[^/]+)';
            $offset = $position + strlen($token);
        }
        return $pattern . preg_quote(substr($path, $offset), '#');
    }
}
