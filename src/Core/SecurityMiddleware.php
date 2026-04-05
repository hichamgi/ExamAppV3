<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

final class SecurityMiddleware
{
    public static function handle(Request $request): void
    {
        $sessionToken = (string) SessionManager::get('session_token', '');
        $user = SessionManager::get('user');

        if ($sessionToken === '' || !is_array($user) || empty($user['id'])) {
            self::fail();
        }

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare(
            "
            SELECT
                us.id,
                us.user_id,
                us.ip_address,
                us.computer_id,
                us.status,
                us.last_activity_at,
                u.can_login,
                u.is_active,
                r.code AS role_code
            FROM user_sessions us
            INNER JOIN users u ON u.id = us.user_id
            INNER JOIN roles r ON r.id = u.role_id
            WHERE us.session_token = :session_token
            LIMIT 1
            "
        );
        $stmt->execute([
            ':session_token' => $sessionToken,
        ]);

        $session = $stmt->fetch();

        if (!$session) {
            self::fail();
        }

        if ((int) ($session['user_id'] ?? 0) !== (int) $user['id']) {
            self::fail();
        }

        if ((string) ($session['status'] ?? '') !== 'active') {
            self::fail();
        }

        $lastActivityAt = (string) ($session['last_activity_at'] ?? '');
        if ($lastActivityAt === '' || (time() - strtotime($lastActivityAt)) > 900) {
            self::fail();
        }

        if (!(bool) ($session['can_login'] ?? false) || !(bool) ($session['is_active'] ?? false)) {
            self::fail();
        }

        $roleCode = (string) ($session['role_code'] ?? '');
        $clientIp = $request->ip();

        if ($clientIp === '' || (string) ($session['ip_address'] ?? '') !== $clientIp) {
            self::fail();
        }

        if ($roleCode === 'student') {
            self::assertSingleActiveStudentSession($pdo, (int) $user['id'], $sessionToken);
            self::assertAllowedStudentComputer($pdo, $clientIp);
        }

        self::assertGlobalFlags($pdo, $roleCode);

        $stmt = $pdo->prepare(
            "
            UPDATE user_sessions
            SET
                last_activity_at = NOW(),
                updated_at = NOW()
            WHERE session_token = :session_token
            "
        );
        $stmt->execute([
            ':session_token' => $sessionToken,
        ]);
    }

    private static function assertSingleActiveStudentSession(\PDO $pdo, int $userId, string $sessionToken): void
    {
        $stmt = $pdo->prepare(
            "
            SELECT COUNT(*) AS total
            FROM user_sessions
            WHERE user_id = :user_id
              AND status = 'active'
              AND session_token <> :session_token
            "
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':session_token' => $sessionToken,
        ]);

        $row = $stmt->fetch();

        if ((int) ($row['total'] ?? 0) > 0) {
            self::fail();
        }
    }

    private static function assertAllowedStudentComputer(\PDO $pdo, string $clientIp): void
    {
        $stmt = $pdo->prepare(
            "
            SELECT id
            FROM lab_computers
            WHERE is_active = 1
              AND (ip_lan = :ip_lan OR ip_wifi = :ip_wifi)
            LIMIT 1
            "
        );
        $stmt->execute([
            ':ip_lan' => $clientIp,
            ':ip_wifi' => $clientIp,
        ]);

        if (!$stmt->fetch()) {
            self::fail();
        }
    }

    private static function assertGlobalFlags(\PDO $pdo, string $roleCode): void
    {
        try {
            $stmt = $pdo->prepare(
                "
                SELECT `key`, `value`
                FROM system_flags
                WHERE `key` IN ('exam_locked', 'student_locked')
                "
            );
            $stmt->execute();

            $flags = [];
            foreach ($stmt->fetchAll() as $row) {
                $flags[(string) $row['key']] = (string) ($row['value'] ?? '');
            }

            if (($flags['exam_locked'] ?? '0') === '1') {
                self::fail();
            }

            if ($roleCode === 'student' && ($flags['student_locked'] ?? '0') === '1') {
                self::fail();
            }
        } catch (Throwable) {
            // Tolérance si la table system_flags n'est pas encore présente.
        }
    }

    private static function fail(): void
    {
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        http_response_code(403);
        exit;
    }
}