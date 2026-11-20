<?php

namespace App\Core;

// Regex-based router. Routes with {param} placeholders dispatch to [Controller, method].
// Parameters named `id` or ending in `Id` only match digits, so `/portfolio/categories`
// can never be mistaken for `/portfolio/{id}`. Other params (e.g. {slug}) match any segment.
class Router
{
    private array $routes = [];

    public function add(string $method, string $pattern, array $handler): void
    {
        $this->routes[] = ['method' => strtoupper($method), 'pattern' => $pattern, 'handler' => $handler];
    }

    public function get(string $p, array $h): void    { $this->add('GET', $p, $h); }
    public function post(string $p, array $h): void   { $this->add('POST', $p, $h); }
    public function put(string $p, array $h): void    { $this->add('PUT', $p, $h); }
    public function patch(string $p, array $h): void  { $this->add('PATCH', $p, $h); }
    public function delete(string $p, array $h): void { $this->add('DELETE', $p, $h); }

    private static function regex(string $pattern): string
    {
        $re = preg_replace_callback('#\{([a-zA-Z_]+)\}#', function (array $m): string {
            $isId = $m[1] === 'id' || str_ends_with($m[1], 'Id');
            return '(?P<' . $m[1] . '>' . ($isId ? '\d+' : '[^/]+') . ')';
        }, $pattern);
        return '#^' . $re . '$#';
    }

    public function dispatch(Request $request): void
    {
        $path = $request->path();
        $method = $request->method();
        $pathMatched = false;

        foreach ($this->routes as $route) {
            if (!preg_match(self::regex($route['pattern']), $path, $matches)) {
                continue;
            }
            $pathMatched = true;
            if ($route['method'] !== $method) {
                continue;
            }
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            [$class, $action] = $route['handler'];
            (new $class())->$action($request, $params);
            return;
        }

        if ($pathMatched) {
            Response::error('Method not allowed', 405);
        }
        Response::error('Resource not found', 404);
    }
}
