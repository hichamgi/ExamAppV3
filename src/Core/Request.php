<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    private array $query;
    private array $request;
    private array $server;
    private array $files;
    private array $cookies;
    private array $headers;
    private ?array $jsonBody = null;
    private string $rawBody;

    public function __construct(
        ?array $query = null,
        ?array $request = null,
        ?array $server = null,
        ?array $files = null,
        ?array $cookies = null,
        ?string $rawBody = null
    ) {
        $this->query = $query ?? $_GET;
        $this->request = $request ?? $_POST;
        $this->server = $server ?? $_SERVER;
        $this->files = $files ?? $_FILES;
        $this->cookies = $cookies ?? $_COOKIE;
        $this->headers = $this->extractHeaders($this->server);
        $this->rawBody = $rawBody ?? file_get_contents('php://input') ?: '';
    }

    public static function capture(): self
    {
        return new self();
    }

    public function method(): string
    {
        $method = strtoupper((string) ($this->server['REQUEST_METHOD'] ?? 'GET'));

        if ($method === 'POST') {
            $override = $this->input('_method');

            if (is_string($override) && $override !== '') {
                $allowed = ['PUT', 'PATCH', 'DELETE'];
                $override = strtoupper($override);

                if (in_array($override, $allowed, true)) {
                    return $override;
                }
            }
        }

        return $method;
    }

    public function uri(): string
    {
        $uri = (string) ($this->server['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : '/';
    }

    public function fullUrl(): string
    {
        $scheme = $this->isHttps() ? 'https' : 'http';
        $host = (string) ($this->server['HTTP_HOST'] ?? 'localhost');
        $uri = (string) ($this->server['REQUEST_URI'] ?? '/');

        return $scheme . '://' . $host . $uri;
    }

    public function path(): string
    {
        $path = $this->uri();
        return '/' . trim($path, '/');
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '');
    }

    public function userAgent(): string
    {
        return (string) ($this->server['HTTP_USER_AGENT'] ?? '');
    }

    public function host(): string
    {
        return (string) ($this->server['HTTP_HOST'] ?? '');
    }

    public function referer(): string
    {
        return (string) ($this->server['HTTP_REFERER'] ?? '');
    }

    public function isHttps(): bool
    {
        if (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off') {
            return true;
        }

        if (isset($this->server['SERVER_PORT']) && (int) $this->server['SERVER_PORT'] === 443) {
            return true;
        }

        if (isset($this->server['HTTP_X_FORWARDED_PROTO']) && $this->server['HTTP_X_FORWARDED_PROTO'] === 'https') {
            return true;
        }

        return false;
    }

    public function isGet(): bool
    {
        return $this->method() === 'GET';
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    public function isPut(): bool
    {
        return $this->method() === 'PUT';
    }

    public function isPatch(): bool
    {
        return $this->method() === 'PATCH';
    }

    public function isDelete(): bool
    {
        return $this->method() === 'DELETE';
    }

    public function isAjax(): bool
    {
        return strtolower($this->header('X-Requested-With', '')) === 'xmlhttprequest';
    }

    public function expectsJson(): bool
    {
        $accept = strtolower($this->header('Accept', ''));
        $contentType = strtolower($this->header('Content-Type', ''));

        return str_contains($accept, 'application/json')
            || str_contains($contentType, 'application/json')
            || $this->isApi();
    }

    public function isApi(): bool
    {
        return str_starts_with($this->path(), '/api/');
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->request[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->request)) {
            return $this->request[$key];
        }

        if (array_key_exists($key, $this->query)) {
            return $this->query[$key];
        }

        $json = $this->json();
        if (array_key_exists($key, $json)) {
            return $json[$key];
        }

        return $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->request, $this->json());
    }

    public function only(array $keys): array
    {
        $data = $this->all();
        $result = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                $result[$key] = $data[$key];
            }
        }

        return $result;
    }

    public function except(array $keys): array
    {
        $data = $this->all();

        foreach ($keys as $key) {
            unset($data[$key]);
        }

        return $data;
    }

    public function has(string $key): bool
    {
        return $this->input($key) !== null;
    }

    public function filled(string $key): bool
    {
        $value = $this->input($key);

        if (is_string($value)) {
            return trim($value) !== '';
        }

        return $value !== null;
    }

    public function boolean(string $key, bool $default = false): bool
    {
        $value = $this->input($key, $default);

        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->input($key, $default);

        if (is_int($value)) {
            return $value;
        }

        return (int) $value;
    }

    public function float(string $key, float $default = 0.0): float
    {
        $value = $this->input($key, $default);

        if (is_float($value)) {
            return $value;
        }

        return (float) $value;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);

        if (is_array($value)) {
            return $default;
        }

        return trim((string) $value);
    }

    public function array(string $key, array $default = []): array
    {
        $value = $this->input($key, $default);

        return is_array($value) ? $value : $default;
    }

    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;

        return is_array($file) ? $file : null;
    }

    public function files(): array
    {
        return $this->files;
    }

    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    public function server(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    public function header(string $key, mixed $default = null): mixed
    {
        $normalizedKey = strtolower($key);

        return $this->headers[$normalizedKey] ?? $default;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function bearerToken(): ?string
    {
        $authorization = (string) $this->header('Authorization', '');

        if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches) !== 1) {
            return null;
        }

        return trim($matches[1]);
    }

    public function rawBody(): string
    {
        return $this->rawBody;
    }

    public function json(): array
    {
        if ($this->jsonBody !== null) {
            return $this->jsonBody;
        }

        if (trim($this->rawBody) === '') {
            $this->jsonBody = [];
            return $this->jsonBody;
        }

        $decoded = json_decode($this->rawBody, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->jsonBody = [];
            return $this->jsonBody;
        }

        $this->jsonBody = is_array($decoded) ? $decoded : [];

        return $this->jsonBody;
    }

    private function extractHeaders(array $server): array
    {
        $headers = [];

        foreach ($server as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            if (str_starts_with($key, 'HTTP_')) {
                $headerName = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$headerName] = is_scalar($value) ? (string) $value : '';
                continue;
            }

            if (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5', 'AUTHORIZATION'], true)) {
                $headerName = strtolower(str_replace('_', '-', $key));
                $headers[$headerName] = is_scalar($value) ? (string) $value : '';
            }
        }

        return $headers;
    }  
}