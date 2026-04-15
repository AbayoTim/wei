<?php
declare(strict_types=1);

class Request
{
    public readonly string $method;
    public readonly string $path;
    public readonly array  $query;
    public readonly array  $body;
    public readonly array  $files;
    public readonly array  $headers;
    public array           $params = [];  // Route params set by Router

    // Auth middleware sets these after token verification
    public ?string $userId   = null;
    public ?array  $userRow  = null;
    public ?string $userRole = null;

    public function __construct()
    {
        $this->method  = $_SERVER['REQUEST_METHOD'];
        $this->path    = $this->parsePath();
        $this->query   = $_GET;
        $this->body    = $this->parseBody();
        $this->files   = $_FILES;
        $this->headers = $this->parseHeaders();
    }

    private function parsePath(): string
    {
        $uri    = $_SERVER['REQUEST_URI'] ?? '/';
        $path   = parse_url($uri, PHP_URL_PATH);
        // Strip the script directory prefix so routes work in a sub-folder too
        $base   = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        return '/' . ltrim($path ?: '/', '/');
    }

    private function parseBody(): array
    {
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($ct, 'application/json')) {
            $raw  = file_get_contents('php://input');
            $data = json_decode($raw, true);
            return is_array($data) ? $data : [];
        }
        // application/x-www-form-urlencoded or multipart/form-data
        return $_POST;
    }

    private function parseHeaders(): array
    {
        if (function_exists('getallheaders')) {
            return array_change_key_case(getallheaders(), CASE_LOWER);
        }
        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $key            = strtolower(str_replace('_', '-', substr($k, 5)));
                $headers[$key]  = $v;
            }
        }
        return $headers;
    }

    public function bearerToken(): ?string
    {
        $auth = $this->headers['authorization'] ?? '';
        if (str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }
        return null;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    public function ip(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $h) {
            if (!empty($_SERVER[$h])) {
                // X-Forwarded-For can be a list; take the first
                $ip = explode(',', $_SERVER[$h])[0];
                return trim($ip);
            }
        }
        return '0.0.0.0';
    }

    public function hasFile(string $field): bool
    {
        return isset($this->files[$field]) && $this->files[$field]['error'] === UPLOAD_ERR_OK;
    }
}
