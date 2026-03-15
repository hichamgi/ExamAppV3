<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected Request $request;
    protected Response $response;

    public function __construct()
    {
        $this->request = Request::capture();
        $this->response = new Response();
    }

    protected function request(): Request
    {
        return $this->request;
    }

    protected function response(): Response
    {
        return $this->response;
    }

    protected function json(array $data, int $status = 200, array $headers = []): void
    {
        $this->response->json($data, $status, $headers);
    }

    protected function html(string $content, int $status = 200, array $headers = []): void
    {
        $this->response->html($content, $status, $headers);
    }

    protected function text(string $content, int $status = 200, array $headers = []): void
    {
        $this->response->text($content, $status, $headers);
    }

    protected function redirect(string $url, int $status = 302): void
    {
        $this->response->redirect($url, $status);
    }

    protected function abort(int $status, string $message = ''): void
    {
        $this->response->abort($status, $message);
    }

    protected function render(string $view, array $data = [], ?string $layout = 'layouts/main'): void
    {
        $viewPath = BASE_PATH . '/src/Views/' . str_replace('.', '/', $view) . '.php';

        if (!is_file($viewPath)) {
            throw new \RuntimeException('Vue introuvable : ' . $view);
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewPath;
        $content = ob_get_clean();

        if ($content === false) {
            throw new \RuntimeException('Impossible de générer la vue : ' . $view);
        }

        if ($layout === null) {
            $this->html($content);
            return;
        }

        $layoutPath = BASE_PATH . '/src/Views/' . str_replace('.', '/', $layout) . '.php';

        if (!is_file($layoutPath)) {
            throw new \RuntimeException('Layout introuvable : ' . $layout);
        }

        ob_start();
        require $layoutPath;
        $finalContent = ob_get_clean();

        if ($finalContent === false) {
            throw new \RuntimeException('Impossible de générer le layout : ' . $layout);
        }

        $this->html($finalContent);
    }

    protected function isPost(): bool
    {
        return $this->request->isPost();
    }

    protected function isGet(): bool
    {
        return $this->request->isGet();
    }

    protected function baseUrl(string $path = ''): string
    {
        $baseUrl = (string) Config::get('app.base_url', '');
        $baseUrl = rtrim($baseUrl, '/');

        if ($path === '') {
            return $baseUrl !== '' ? $baseUrl : '/';
        }

        return $baseUrl . '/' . ltrim($path, '/');
    }
}