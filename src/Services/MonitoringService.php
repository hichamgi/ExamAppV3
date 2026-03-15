<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class MonitoringService
{
    public function getDashboardStats(): array
    {
        return [
            'active_sessions' => (int) Database::fetchValue(
                "SELECT COUNT(*) FROM user_sessions WHERE status = 'active'",
                [],
                0
            ),
            'active_students' => (int) Database::fetchValue(
                "
                SELECT COUNT(*)
                FROM user_sessions us
                INNER JOIN users u ON u.id = us.user_id
                INNER JOIN roles r ON r.id = u.role_id
                WHERE us.status = 'active'
                  AND r.code = 'student'
                ",
                [],
                0
            ),
            'open_alerts' => (int) Database::fetchValue(
                "SELECT COUNT(*) FROM login_attempt_alerts WHERE status IN ('refused', 'suspect')",
                [],
                0
            ),
            'active_computers' => (int) Database::fetchValue(
                "SELECT COUNT(*) FROM lab_computers WHERE is_active = 1",
                [],
                0
            ),
        ];
    }

    public function getActiveSessions(): array
    {
        $rows = Database::fetchAll(
            "
            SELECT
                us.id,
                us.user_id,
                us.class_id,
                us.computer_id,
                us.ip_address,
                us.network_type,
                us.status,
                us.started_at,
                us.last_activity_at,
                u.numero,
                u.code_massar,
                u.nom,
                u.prenom,
                c.name AS class_name,
                r.code AS role_code,
                lc.name AS computer_name,
                lc.hostname,
                lc.room_name
            FROM user_sessions us
            INNER JOIN users u ON u.id = us.user_id
            INNER JOIN roles r ON r.id = u.role_id
            LEFT JOIN classes c ON c.id = us.class_id
            LEFT JOIN lab_computers lc ON lc.id = us.computer_id
            WHERE us.status = 'active'
            ORDER BY us.last_activity_at DESC
            "
        );

        return array_map(
            static fn(array $row): array => [
                'id' => (int) $row['id'],
                'user_id' => (int) $row['user_id'],
                'class_id' => isset($row['class_id']) ? (int) $row['class_id'] : null,
                'computer_id' => isset($row['computer_id']) ? (int) $row['computer_id'] : null,
                'role' => (string) $row['role_code'],
                'numero' => (int) ($row['numero'] ?? 0),
                'code_massar' => (string) ($row['code_massar'] ?? ''),
                'student_name' => trim(((string) $row['nom']) . ' ' . ((string) $row['prenom'])),
                'class_name' => (string) ($row['class_name'] ?? ''),
                'computer_name' => (string) ($row['computer_name'] ?? ''),
                'hostname' => (string) ($row['hostname'] ?? ''),
                'room_name' => (string) ($row['room_name'] ?? ''),
                'ip_address' => (string) ($row['ip_address'] ?? ''),
                'network_type' => (string) ($row['network_type'] ?? 'unknown'),
                'status' => (string) ($row['status'] ?? ''),
                'started_at' => (string) ($row['started_at'] ?? ''),
                'last_activity_at' => (string) ($row['last_activity_at'] ?? ''),
            ],
            $rows
        );
    }

    public function getRecentAlerts(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));

        $rows = Database::fetchAll(
            "
            SELECT
                la.id,
                la.user_id,
                la.username_attempted,
                la.class_id,
                la.existing_session_id,
                la.existing_computer_id,
                la.existing_ip,
                la.attempted_computer_id,
                la.attempted_ip,
                la.attempted_network_type,
                la.attempted_at,
                la.status,
                la.notes,
                la.created_at,
                u.numero,
                u.nom,
                u.prenom,
                c.name AS class_name,
                existing_pc.name AS existing_computer_name,
                attempted_pc.name AS attempted_computer_name
            FROM login_attempt_alerts la
            LEFT JOIN users u ON u.id = la.user_id
            LEFT JOIN classes c ON c.id = la.class_id
            LEFT JOIN lab_computers existing_pc ON existing_pc.id = la.existing_computer_id
            LEFT JOIN lab_computers attempted_pc ON attempted_pc.id = la.attempted_computer_id
            ORDER BY la.attempted_at DESC
            LIMIT {$limit}
            "
        );

        return array_map(
            static fn(array $row): array => [
                'id' => (int) $row['id'],
                'user_id' => isset($row['user_id']) ? (int) $row['user_id'] : null,
                'numero' => isset($row['numero']) ? (int) $row['numero'] : null,
                'student_name' => trim(((string) ($row['nom'] ?? '')) . ' ' . ((string) ($row['prenom'] ?? ''))),
                'username_attempted' => (string) ($row['username_attempted'] ?? ''),
                'class_id' => isset($row['class_id']) ? (int) $row['class_id'] : null,
                'class_name' => (string) ($row['class_name'] ?? ''),
                'existing_session_id' => isset($row['existing_session_id']) ? (int) $row['existing_session_id'] : null,
                'existing_computer_name' => (string) ($row['existing_computer_name'] ?? ''),
                'existing_ip' => (string) ($row['existing_ip'] ?? ''),
                'attempted_computer_name' => (string) ($row['attempted_computer_name'] ?? ''),
                'attempted_ip' => (string) ($row['attempted_ip'] ?? ''),
                'attempted_network_type' => (string) ($row['attempted_network_type'] ?? 'unknown'),
                'attempted_at' => (string) ($row['attempted_at'] ?? ''),
                'status' => (string) ($row['status'] ?? ''),
                'notes' => (string) ($row['notes'] ?? ''),
            ],
            $rows
        );
    }

    public function getRoomOverview(): array
    {
        $rows = Database::fetchAll(
            "
            SELECT
                lc.id,
                lc.name,
                lc.hostname,
                lc.ip_lan,
                lc.ip_wifi,
                lc.is_active,
                lc.room_name,
                lc.description,
                us.id AS session_id,
                us.user_id,
                us.class_id,
                us.ip_address,
                us.network_type,
                us.started_at,
                us.last_activity_at,
                u.numero,
                u.nom,
                u.prenom,
                c.name AS class_name
            FROM lab_computers lc
            LEFT JOIN user_sessions us
                ON us.computer_id = lc.id
               AND us.status = 'active'
            LEFT JOIN users u ON u.id = us.user_id
            LEFT JOIN classes c ON c.id = us.class_id
            ORDER BY lc.name ASC
            "
        );

        return array_map(
            static fn(array $row): array => [
                'computer_id' => (int) $row['id'],
                'computer_name' => (string) ($row['name'] ?? ''),
                'hostname' => (string) ($row['hostname'] ?? ''),
                'ip_lan' => (string) ($row['ip_lan'] ?? ''),
                'ip_wifi' => (string) ($row['ip_wifi'] ?? ''),
                'is_active' => (bool) ($row['is_active'] ?? false),
                'room_name' => (string) ($row['room_name'] ?? ''),
                'description' => (string) ($row['description'] ?? ''),
                'occupied' => !empty($row['session_id']),
                'session' => !empty($row['session_id']) ? [
                    'session_id' => (int) $row['session_id'],
                    'user_id' => (int) $row['user_id'],
                    'class_id' => isset($row['class_id']) ? (int) $row['class_id'] : null,
                    'ip_address' => (string) ($row['ip_address'] ?? ''),
                    'network_type' => (string) ($row['network_type'] ?? 'unknown'),
                    'started_at' => (string) ($row['started_at'] ?? ''),
                    'last_activity_at' => (string) ($row['last_activity_at'] ?? ''),
                    'numero' => isset($row['numero']) ? (int) $row['numero'] : null,
                    'student_name' => trim(((string) ($row['nom'] ?? '')) . ' ' . ((string) ($row['prenom'] ?? ''))),
                    'class_name' => (string) ($row['class_name'] ?? ''),
                ] : null,
            ],
            $rows
        );
    }
}