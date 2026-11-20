<?php

namespace App\Core;

// Wraps the incoming HTTP request: method, path, JSON or form body, query, files, bearer token.
class Request
{
    private string $method;
    private string $path;
    private array $body;
    private array $query;

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->query  = $_GET;

        // Path relative to the app base, with /api kept. e.g. /jovisco/api/portfolio/5 -> /api/portfolio/5
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $uri = rawurldecode($uri);
        $apiPos = strpos($uri, '/api');
        $this->path = $apiPos !== false ? substr($uri, $apiPos) : $uri;
        $this->path = rtrim($this->path, '/') ?: '/';

        // JSON bodies (most endpoints) or multipart form fields (image uploads).
        $raw = file_get_contents('php://input') ?: '';
        $decoded = json_decode($raw, true);
        $isJson = str_contains(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json');
        if ($isJson && trim($raw) !== '' && !is_array($decoded)) {
            Response::error('The request body is not valid JSON (it must be UTF-8)', 400);
        }
        $this->body = is_array($decoded) ? $decoded : $_POST;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function body(): array
    {
        return $this->body;
    }

    public function input(string $key, $default = null)
    {
        return $this->body[$key] ?? $default;
    }

    public function query(string $key, $default = null)
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * Uploaded files for a field, normalised to a list of $_FILES-style entries. Accepts both
     * `images` and `images[]` (multiple) uploads.
     * @return list<array{name: string, type: string, tmp_name: string, error: int, size: int}>
     */
    public function files(string $field): array
    {
        $f = $_FILES[$field] ?? null;
        if (!$f) {
            return [];
        }
        if (!is_array($f['name'])) {
            return [$f];
        }
        $out = [];
        foreach (array_keys($f['name']) as $i) {
            $out[] = [
                'name' => $f['name'][$i], 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i],
                'error' => $f['error'][$i], 'size' => $f['size'][$i],
            ];
        }
        return $out;
    }

    public function bearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if ($header === '' && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }
        if (preg_match('/Bearer\s+(.+)/i', $header, $m)) {
            return trim($m[1]);
        }
        return null;
    }
}
