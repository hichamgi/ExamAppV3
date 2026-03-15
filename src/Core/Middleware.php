<?php

declare(strict_types=1);

namespace App\Core;

abstract class Middleware
{
    protected Request $request;
    protected Response $response;

    public function __construct(?Request $request = null, ?Response $response = null)
    {
        $this->request = $request ?? Request::capture();
        $this->response = $response ?? new Response();
    }

    abstract public function handle(...$arguments): void;

    protected function request(): Request
    {
        return $this->request;
    }

    protected function response(): Response
    {
        return $this->response;
    }

    protected function abort(int $status, string $message = ''): void
    {
        $this->response->abort($status, $message);
        exit;
    }

    protected function redirect(string $url, int $status = 302): void
    {
        $this->response->redirect($url, $status);
        exit;
    }

    protected function json(array $data, int $status = 200): void
    {
        $this->response->json($data, $status);
        exit;
    }
}