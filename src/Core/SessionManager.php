<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class SessionManager
{
    private const AUTH_KEY = 'auth';
    private const META_KEY = '_session_meta';

    public static function boot(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!isset($_SESSION[self::META_KEY]) || !is_array($_SESSION[self::META_KEY])) {
            $_SESSION[self::META_KEY] = [
                'created_at' => time(),
                'last_activity_at' => time(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ];
        }
    }

    public static function login(array $authData, array $dbSessionData = []): void
    {
        self::boot();

        session_regenerate_id(true);

        $now = time();
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');

        $authData['login_at'] = $authData['login_at'] ?? $now;
        $authData['last_activity_at'] = $now;
        $authData['ip'] = $authData['ip'] ?? $ip;
        $authData['user_agent'] = $authData['user_agent'] ?? $userAgent;

        if (!isset($authData['session_token']) || !is_string($authData['session_token']) || $authData['session_token'] === '') {
            $authData['session_token'] = bin2hex(random_bytes(32));
        }

        $_SESSION[self::AUTH_KEY] = $authData;
        $_SESSION[self::META_KEY] = [
            'created_at' => $now,
            'last_activity_at' => $now,
            'ip' => $ip,
            'user_agent' => $userAgent,
        ];

        if ($dbSessionData !== []) {
            self::createDatabaseSession($dbSessionData + [
                'session_token' => hash('sha256', $authData['session_token']),
                'user_id' => (int) ($authData['user_id'] ?? 0),
                'class_id' => isset($authData['class_id']) ? (int) $authData['class_id'] : null,
                'ip_address' => $ip,
                'user_agent' => mb_substr($userAgent, 0, 255),
            ]);
        }
    }

    public static function logout(bool $destroyPhpSession = true, bool $closeDatabaseSession = true): void
    {
        self::boot();

        if ($closeDatabaseSession) {
            self::closeCurrentDatabaseSession();
        }

        unset($_SESSION[self::AUTH_KEY]);

        if ($destroyPhpSession) {
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

    public static function auth(): ?array
    {
        self::boot();

        $auth = $_SESSION[self::AUTH_KEY] ?? null;

        return is_array($auth) ? $auth : null;
    }

    public static function check(): bool
    {
        return self::auth() !== null;
    }

    public static function guest(): bool
    {
        return !self::check();
    }

    public static function id(): ?int
    {
        $auth = self::auth();
        $id = $auth['user_id'] ?? null;

        return is_numeric($id) ? (int) $id : null;
    }

    public static function role(): ?string
    {
        $auth = self::auth();
        $role = $auth['role'] ?? null;

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

    public static function sessionToken(): ?string
    {
        $auth = self::auth();
        $token = $auth['session_token'] ?? null;

        return is_string($token) && $token !== '' ? $token : null;
    }

    public static function touch(bool $updateDatabase = false): void
    {
        self::boot();

        if (!isset($_SESSION[self::AUTH_KEY]) || !is_array($_SESSION[self::AUTH_KEY])) {
            return;
        }

        $now = time();

        $_SESSION[self::AUTH_KEY]['last_activity_at'] = $now;
        $_SESSION[self::META_KEY]['last_activity_at'] = $now;

        if ($updateDatabase) {
            self::refreshDatabaseSession();
        }
    }

    public static function enforceTimeout(?int $timeoutMinutes = null): void
    {
        self::boot();

        if (!self::check()) {
            return;
        }

        $timeoutMinutes ??= self::resolveTimeoutMinutesByRole();
        $timeoutSeconds = max(60, $timeoutMinutes * 60);

        $lastActivity = (int) ($_SESSION[self::AUTH_KEY]['last_activity_at'] ?? time());

        if ((time() - $lastActivity) > $timeoutSeconds) {
            self::logout(true, true);

            $response = new Response();
            $response->abort(440, 'Session expirée.');
            exit;
        }
    }

    public static function enforceIntegrity(bool $checkIp = false, bool $checkUserAgent = true): void
    {
        self::boot();

        $auth = self::auth();
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

        self::logout(true, true);

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

    public static function currentDatabaseSession(): ?array
    {
        $userId = self::id();
        $token = self::sessionToken();

        if ($userId === null || $token === null) {
            return null;
        }

        return Database::fetchOne(
            "SELECT *
             FROM user_sessions
             WHERE user_id = :user_id
               AND session_token = :session_token
               AND status = 'active'
             LIMIT 1",
            [
                'user_id' => $userId,
                'session_token' => hash('sha256', $token),
            ]
        );
    }

    public static function findActiveUserSession(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        return Database::fetchOne(
            "SELECT *
             FROM user_sessions
             WHERE user_id = :user_id
               AND status = 'active'
             ORDER BY id DESC
             LIMIT 1",
            ['user_id' => $userId]
        );
    }

    public static function findActiveStudentSession(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        return Database::fetchOne(
            "SELECT us.*
             FROM user_sessions us
             INNER JOIN users u ON u.id = us.user_id
             INNER JOIN roles r ON r.id = u.role_id
             WHERE us.user_id = :user_id
               AND us.status = 'active'
               AND r.code = 'student'
             ORDER BY us.id DESC
             LIMIT 1",
            ['user_id' => $userId]
        );
    }

    public static function refreshDatabaseSession(): void
    {
        $userId = self::id();
        $token = self::sessionToken();

        if ($userId === null || $token === null) {
            return;
        }

        Database::execute(
            "UPDATE user_sessions
             SET last_activity_at = NOW(),
                 updated_at = NOW()
             WHERE user_id = :user_id
               AND session_token = :session_token
               AND status = 'active'",
            [
                'user_id' => $userId,
                'session_token' => hash('sha256', $token),
            ]
        );
    }

    public static function closeCurrentDatabaseSession(): void
    {
        $userId = self::id();
        $token = self::sessionToken();

        if ($userId === null || $token === null) {
            return;
        }

        Database::execute(
            "UPDATE user_sessions
             SET status = 'closed',
                 closed_at = NOW(),
                 updated_at = NOW()
             WHERE user_id = :user_id
               AND session_token = :session_token
               AND status = 'active'",
            [
                'user_id' => $userId,
                'session_token' => hash('sha256', $token),
            ]
        );
    }

    public static function closeSessionById(int $sessionId): int
    {
        if ($sessionId <= 0) {
            return 0;
        }

        return Database::execute(
            "UPDATE user_sessions
             SET status = 'closed',
                 closed_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id
               AND status = 'active'",
            ['id' => $sessionId]
        );
    }

    public static function closeAllActiveStudentSessions(int $userId, ?string $exceptHashedSessionToken = null): void
    {
        $params = ['user_id' => $userId];
        $sql = "UPDATE user_sessions
                SET status = 'closed',
                    closed_at = NOW(),
                    updated_at = NOW()
                WHERE user_id = :user_id
                  AND status = 'active'";

        if ($exceptHashedSessionToken !== null && $exceptHashedSessionToken !== '') {
            $sql .= " AND session_token <> :session_token";
            $params['session_token'] = $exceptHashedSessionToken;
        }

        Database::execute($sql, $params);
    }

    public static function isCurrentSessionMatchingComputer(int $computerId, string $ip): bool
    {
        $session = self::currentDatabaseSession();

        if ($session === null) {
            return false;
        }

        $sessionComputerId = (int) ($session['computer_id'] ?? 0);
        $sessionIp = (string) ($session['ip_address'] ?? '');

        if ($sessionComputerId <= 0 || $computerId <= 0) {
            return false;
        }

        if ($sessionComputerId !== $computerId) {
            return false;
        }

        if ($sessionIp !== '' && $ip !== '' && !hash_equals($sessionIp, $ip)) {
            return false;
        }

        return true;
    }

    public static function expireInactiveSessions(?int $timeoutMinutes = null): int
    {
        $timeoutMinutes ??= (int) Config::get('app.session.timeout', 15);
        $timeoutMinutes = max(1, $timeoutMinutes);

        return Database::execute(
            "UPDATE user_sessions
             SET status = 'expired',
                 closed_at = NOW(),
                 updated_at = NOW()
             WHERE status = 'active'
               AND last_activity_at < (NOW() - INTERVAL :timeout MINUTE)",
            [
                'timeout' => $timeoutMinutes,
            ]
        );
    }

    private static function createDatabaseSession(array $data): void
    {
        $userId = (int) ($data['user_id'] ?? 0);

        if ($userId <= 0) {
            throw new RuntimeException('Impossible de créer la session DB : user_id invalide.');
        }

        Database::insert('user_sessions', [
            'session_token' => (string) $data['session_token'],
            'user_id' => $userId,
            'class_id' => isset($data['class_id']) ? (int) $data['class_id'] : null,
            'computer_id' => isset($data['computer_id']) ? (int) $data['computer_id'] : null,
            'ip_address' => (string) ($data['ip_address'] ?? ''),
            'user_agent' => mb_substr((string) ($data['user_agent'] ?? ''), 0, 255),
            'network_type' => (string) ($data['network_type'] ?? 'unknown'),
            'status' => 'active',
            'started_at' => date('Y-m-d H:i:s'),
            'last_activity_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
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

    private static function resolveTimeoutMinutesByRole(): int
    {
        $role = self::role();

        return match ($role) {
            'admin' => max(30, (int) Config::get('app.session.admin_timeout', 240)),
            'student' => max(30, (int) Config::get('app.session.student_timeout', 90)),
            default => 15,
        };
    }
}