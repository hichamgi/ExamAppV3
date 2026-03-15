<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    public function html(string $content, int $status = 200, array $headers = []): void
    {
        $this->sendHeaders($status, array_merge([
            'Content-Type' => 'text/html; charset=UTF-8',
        ], $headers));

        echo $content;
    }

    public function json(array $data, int $status = 200, array $headers = []): void
    {
        $this->sendHeaders($status, array_merge([
            'Content-Type' => 'application/json; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ], $headers));

        echo json_encode(
            $data,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_INVALID_UTF8_SUBSTITUTE
        );
    }

    public function text(string $content, int $status = 200, array $headers = []): void
    {
        $this->sendHeaders($status, array_merge([
            'Content-Type' => 'text/plain; charset=UTF-8',
        ], $headers));

        echo $content;
    }

    public function redirect(string $url, int $status = 302): void
    {
        $this->sendHeaders($status, [
            'Location' => $url,
        ]);
    }

    public function download(
        string $filePath,
        ?string $downloadName = null,
        string $contentType = 'application/octet-stream'
    ): void {
        if (!is_file($filePath) || !is_readable($filePath)) {
            $this->json([
                'success' => false,
                'message' => 'Fichier introuvable.',
            ], 404);
            return;
        }

        $downloadName ??= basename($filePath);
        $fileSize = filesize($filePath);

        $this->sendHeaders(200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="' . addslashes($downloadName) . '"',
            'Content-Length' => (string) $fileSize,
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            'Pragma' => 'public',
        ]);

        readfile($filePath);
    }

    public function noContent(): void
    {
        $this->sendHeaders(204, []);
    }

    public function abort(int $status, string $message = ''): void
    {
        if ($this->wantsJsonByContext()) {
            $this->json([
                'success' => false,
                'message' => $message !== '' ? $message : 'Erreur HTTP ' . $status,
            ], $status);
            return;
        }

        $this->html(
            '<h1>' . $status . '</h1><p>' . htmlspecialchars($message !== '' ? $message : 'Erreur HTTP', ENT_QUOTES, 'UTF-8') . '</p>',
            $status
        );
    }

    private function sendHeaders(int $status, array $headers): void
    {
        if (!headers_sent()) {
            http_response_code($status);

            foreach ($headers as $name => $value) {
                header($name . ': ' . $value, true);
            }
        }
    }

    private function wantsJsonByContext(): bool
    {
        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));

        return str_starts_with(parse_url($requestUri, PHP_URL_PATH) ?: '', '/api/')
            || str_contains($accept, 'application/json')
            || $requestedWith === 'xmlhttprequest';
    }
}