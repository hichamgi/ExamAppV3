<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use RuntimeException;

final class Router
{
    /**
     * @var array<string, array<int, array{path:string, handler:callable|array|string, regex:string, paramNames:array<int,string>}>>
     */
    private array $routes = [];

    public function get(string $path, callable|array|string $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, callable|array|string $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, callable|array|string $handler): void
    {
        $this->addRoute('PUT', $path, $handler);
    }

    public function patch(string $path, callable|array|string $handler): void
    {
        $this->addRoute('PATCH', $path, $handler);
    }

    public function delete(string $path, callable|array|string $handler): void
    {
        $this->addRoute('DELETE', $path, $handler);
    }

    public function any(string $path, callable|array|string $handler): void
    {
        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->addRoute($method, $path, $handler);
        }
    }

    public function dispatch(string $method, string $uri): bool
    {
        $method = strtoupper(trim($method));
        $path = $this->normalizePath($uri);

        if (!isset($this->routes[$method])) {
            return false;
        }

        foreach ($this->routes[$method] as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            $params = $this->extractParams($matches, $route['paramNames']);
            $response = $this->invokeHandler($route['handler'], $params);

            if ($response !== null) {
                echo $response;
            }

            return true;
        }

        return false;
    }

    private function addRoute(string $method, string $path, callable|array|string $handler): void
    {
        $normalizedPath = $this->normalizePath($path);
        [$regex, $paramNames] = $this->compilePathToRegex($normalizedPath);

        $this->routes[$method][] = [
            'path' => $normalizedPath,
            'handler' => $handler,
            'regex' => $regex,
            'paramNames' => $paramNames,
        ];
    }

    /**
     * Convertit /users/{id}/exam/{examId} en regex.
     *
     * @return array{0:string,1:array<int,string>}
     */
    private function compilePathToRegex(string $path): array
    {
        $paramNames = [];

        $regex = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#',
            function (array $matches) use (&$paramNames): string {
                $paramNames[] = $matches[1];
                return '([^/]+)';
            },
            $path
        );

        if ($regex === null) {
            throw new RuntimeException('Impossible de compiler la route : ' . $path);
        }

        return ['#^' . $regex . '$#', $paramNames];
    }

    /**
     * @param array<int|string, string> $matches
     * @param array<int, string> $paramNames
     * @return array<string, string>
     */
    private function extractParams(array $matches, array $paramNames): array
    {
        $params = [];

        foreach ($paramNames as $index => $name) {
            $params[$name] = $matches[$index + 1] ?? null;
        }

        return $params;
    }

    private function normalizePath(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            return '/';
        }

        $path = parse_url($path, PHP_URL_PATH) ?: '/';
        $path = '/' . trim($path, '/');

        return $path === '//' ? '/' : $path;
    }

    private function invokeHandler(callable|array|string $handler, array $params): mixed
    {
        if (is_callable($handler)) {
            return $this->invokeCallable($handler, $params);
        }

        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;

            if (!class_exists($class)) {
                throw new RuntimeException(sprintf('Contrôleur introuvable : %s', $class));
            }

            $controller = new $class();

            if (!method_exists($controller, $method)) {
                throw new RuntimeException(sprintf('Méthode introuvable : %s::%s', $class, $method));
            }

            return $this->invokeCallable([$controller, $method], $params);
        }

        if (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler, 2);

            if (!class_exists($class)) {
                throw new RuntimeException(sprintf('Contrôleur introuvable : %s', $class));
            }

            $controller = new $class();

            if (!method_exists($controller, $method)) {
                throw new RuntimeException(sprintf('Méthode introuvable : %s::%s', $class, $method));
            }

            return $this->invokeCallable([$controller, $method], $params);
        }

        throw new RuntimeException('Handler de route invalide.');
    }

    private function invokeCallable(callable $handler, array $params): mixed
    {
        if ($handler instanceof Closure) {
            return $handler(...array_values($params));
        }

        return call_user_func_array($handler, $params);
    }
}