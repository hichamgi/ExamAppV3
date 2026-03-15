<?php

declare(strict_types=1);

namespace App\Core;

final class AuthMiddleware
{
    private const AUTH_SESSION_KEY = 'auth';

    public static function user(): ?array
    {
        self::ensureSessionStarted();

        $auth = $_SESSION[self::AUTH_SESSION_KEY] ?? null;

        return is_array($auth) ? $auth : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function guest(): bool
    {
        return !self::check();
    }

    public static function id(): ?int
    {
        $user = self::user();

        if (!is_array($user)) {
            return null;
        }

        $userId = $user['user_id'] ?? null;

        return is_numeric($userId) ? (int) $userId : null;
    }

    public static function role(): ?string
    {
        $user = self::user();

        if (!is_array($user)) {
            return null;
        }

        $role = $user['role'] ?? null;

        return is_string($role) && $role !== '' ? $role : null;
    }

    public static function isAdmin(): bool
    {
        return self::role() === 'admin';
    }

    public static function isStudent(): bool
    {
        return self::role() === 'student';
    }

    public static function login(array $authData, bool $regenerateSessionId = true): void
    {
        self::ensureSessionStarted();

        if ($regenerateSessionId) {
            session_regenerate_id(true);
        }

        $authData['login_at'] = $authData['login_at'] ?? time();
        $authData['last_activity_at'] = time();
        $authData['ip'] = $authData['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? '');
        $authData['user_agent'] = $authData['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? '');

        $_SESSION[self::AUTH_SESSION_KEY] = $authData;
    }

    public static function logout(bool $destroySession = false): void
    {
        self::ensureSessionStarted();

        unset($_SESSION[self::AUTH_SESSION_KEY]);

        if ($destroySession) {
            $_SESSION = [];

            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();

                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'] ?? '/',
                    $params['domain'] ?? '',
                    (bool) ($params['secure'] ?? false),
                    (bool) ($params['httponly'] ?? true)
                );
            }

            session_destroy();
        }
    }

    public static function touch(): void
    {
        self::ensureSessionStarted();

        if (!isset($_SESSION[self::AUTH_SESSION_KEY]) || !is_array($_SESSION[self::AUTH_SESSION_KEY])) {
            return;
        }

        $_SESSION[self::AUTH_SESSION_KEY]['last_activity_at'] = time();
    }

    public static function enforceSessionTimeout(?int $timeoutMinutes = null): void
    {
        self::ensureSessionStarted();

        if (!self::check()) {
            return;
        }

        $timeoutMinutes ??= (int) Config::get('app.session.timeout', 15);
        $timeoutSeconds = max(60, $timeoutMinutes * 60);

        $lastActivity = (int) ($_SESSION[self::AUTH_SESSION_KEY]['last_activity_at'] ?? time());

        if ((time() - $lastActivity) > $timeoutSeconds) {
            self::logout(true);

            $response = new Response();
            $response->abort(440, 'Session expirée.');
            exit;
        }

        self::touch();
    }

    public static function enforceGuest(?string $redirectUrl = null): void
    {
        self::ensureSessionStarted();

        if (!self::check()) {
            return;
        }

        $response = new Response();
        $response->redirect($redirectUrl ?? self::baseUrl('/'));
        exit;
    }

    public static function enforceAuth(?string $redirectUrl = null): void
    {
        self::ensureSessionStarted();

        if (self::check()) {
            self::enforceSessionTimeout();
            return;
        }

        $request = Request::capture();
        $response = new Response();

        if ($request->expectsJson()) {
            $response->json([
                'success' => false,
                'message' => 'Authentification requise.',
            ], 401);
            exit;
        }

        $response->redirect($redirectUrl ?? self::baseUrl('/login'));
        exit;
    }

    public static function enforceRole(string|array $roles, ?string $redirectUrl = null): void
    {
        self::enforceAuth($redirectUrl);

        $roles = is_array($roles) ? $roles : [$roles];
        $currentRole = self::role();

        if ($currentRole !== null && in_array($currentRole, $roles, true)) {
            return;
        }

        $request = Request::capture();
        $response = new Response();

        if ($request->expectsJson()) {
            $response->json([
                'success' => false,
                'message' => 'Accès interdit.',
            ], 403);
            exit;
        }

        $response->redirect($redirectUrl ?? self::baseUrl('/forbidden'));
        exit;
    }

    public static function enforceAdmin(?string $redirectUrl = null): void
    {
        self::enforceRole('admin', $redirectUrl);
    }

    public static function enforceStudent(?string $redirectUrl = null): void
    {
        self::enforceRole('student', $redirectUrl);
    }

    public static function enforceSessionIntegrity(
        bool $checkIp = false,
        bool $checkUserAgent = true
    ): void {
        self::enforceAuth();

        $auth = self::user();
        if ($auth === null) {
            return;
        }

        $currentIp = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $currentUserAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');

        $sessionIp = (string) ($auth['ip'] ?? '');
        $sessionUserAgent = (string) ($auth['user_agent'] ?? '');

        $invalid = false;

        if ($checkIp && $sessionIp !== '' && $currentIp !== '' && !hash_equals($sessionIp, $currentIp)) {
            $invalid = true;
        }

        if ($checkUserAgent && $sessionUserAgent !== '' && $currentUserAgent !== '' && !hash_equals($sessionUserAgent, $currentUserAgent)) {
            $invalid = true;
        }

        if (!$invalid) {
            return;
        }

        self::logout(true);

        $request = Request::capture();
        $response = new Response();

        if ($request->expectsJson()) {
            $response->json([
                'success' => false,
                'message' => 'Session invalide.',
            ], 401);
            exit;
        }

        $response->redirect(self::baseUrl('/login'));
        exit;
    }

    public static function requireAdminPage(): void
    {
        self::enforceAdmin();
        self::enforceSessionIntegrity(false, true);
    }

    public static function requireStudentPage(): void
    {
        self::enforceStudent();
        self::enforceSessionIntegrity(false, true);
    }

    public static function requireApiAuth(): void
    {
        self::enforceAuth();
        self::enforceSessionIntegrity(false, true);
    }

    public static function requireApiRole(string|array $roles): void
    {
        self::enforceRole($roles);
        self::enforceSessionIntegrity(false, true);
    }

    private static function baseUrl(string $path = ''): string
    {
        $baseUrl = (string) Config::get('app.base_url', '');
        $baseUrl = rtrim($baseUrl, '/');

        if ($path === '') {
            return $baseUrl !== '' ? $baseUrl : '/';
        }

        return $baseUrl . '/' . ltrim($path, '/');
    }

    private static function ensureSessionStarted(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}