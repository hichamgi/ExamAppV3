<?php

namespace App\Core;

use App\Core\Database;

class SecurityMiddleware
{
    public static function handle(Request $request): void
    {
        $sessionToken = SessionManager::get('session_token');
        $user = SessionManager::get('user');

        if (!$sessionToken || !$user) {
            self::fail();
        }

        $pdo = Database::getConnection();

        // ===== SESSION DB =====
        $stmt = $pdo->prepare("
            SELECT user_id, ip_address, status, last_activity_at
            FROM user_sessions
            WHERE session_token = :token
            LIMIT 1
        ");
        $stmt->execute([':token' => $sessionToken]);

        $session = $stmt->fetch();

        if (!$session || $session['status'] !== 'active') {
            self::fail();
        }

        if (time() - strtotime($session['last_activity_at']) > 900) {
            self::fail();
        }

        // ===== DOUBLE SESSION BLOCK =====
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM user_sessions 
            WHERE user_id = :user_id AND status = 'active'
        ");
        $stmt->execute([':user_id' => $user['id']]);

        $count = $stmt->fetch();

        if (($count['total'] ?? 0) > 1) {
            self::fail();
        }

        // ===== IP LOCK =====
        if ($session['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
            self::fail();
        }

        // ===== USER ACTIVE =====
        $stmt = $pdo->prepare("
            SELECT can_login, is_active
            FROM users
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $user['id']]);

        $u = $stmt->fetch();

        if (!$u || !$u['can_login'] || !$u['is_active']) {
            self::fail();
        }

        // ===== COMPUTER AUTH =====
        $stmt = $pdo->prepare("
            SELECT id FROM lab_computers
            WHERE ip_lan = :ip AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([':ip' => $_SERVER['REMOTE_ADDR']]);

        if (!$stmt->fetch()) {
            self::fail();
        }

        // ===== GLOBAL LOCK =====
        $stmt = $pdo->prepare("
            SELECT value FROM system_flags 
            WHERE `key` = 'exam_locked' 
            LIMIT 1
        ");
        $stmt->execute();

        $flag = $stmt->fetch();

        if ($flag && $flag['value'] === '1') {
            self::fail();
        }

        // ===== REFRESH ACTIVITY =====
        $stmt = $pdo->prepare("
            UPDATE user_sessions
            SET last_activity_at = NOW()
            WHERE session_token = :token
        ");
        $stmt->execute([':token' => $sessionToken]);
    }

    private static function fail(): void
    {
        session_destroy();
        http_response_code(403);
        exit;
    }
}