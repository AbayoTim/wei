<?php
declare(strict_types=1);

class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->routes[] = ['GET', $path, $handler];
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes[] = ['POST', $path, $handler];
    }

    public function put(string $path, callable $handler): void
    {
        $this->routes[] = ['PUT', $path, $handler];
    }

    public function delete(string $path, callable $handler): void
    {
        $this->routes[] = ['DELETE', $path, $handler];
    }

    public function dispatch(Request $req): void
    {
        $method = $req->method;

        // Handle OPTIONS preflight
        if ($method === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        foreach ($this->routes as [$routeMethod, $routePath, $handler]) {
            if ($routeMethod !== $method) continue;

            $pattern = preg_replace_callback(
                '/:([a-zA-Z_][a-zA-Z0-9_]*)/',
                fn($m) => '(?P<' . $m[1] . '>[^/]+)',
                $routePath
            );
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $req->path, $matches)) {
                // Extract named capture groups (route params)
                foreach ($matches as $k => $v) {
                    if (is_string($k)) $req->params[$k] = urldecode($v);
                }
                call_user_func($handler, $req);
                return;
            }
        }

        Response::error('Not found.', 404);
    }
}
