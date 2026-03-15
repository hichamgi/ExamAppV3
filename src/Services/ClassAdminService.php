<?php

namespace App\Services;

use App\Core\Database;

class ClassAdminService
{
    public function listClasses(): array
    {
        $rows = Database::fetchAll(
            "
            SELECT
                c.id,
                c.name,
                c.school_year,
                c.is_active,
                c.created_at,
                c.updated_at,
                COUNT(DISTINCT CASE WHEN u.is_active = 1 AND u.numero > 0 THEN cs.user_id END) AS students_count,
                COUNT(DISTINCT CASE WHEN u.is_active = 1 AND u.numero > 0 AND u.can_login = 1 THEN cs.user_id END) AS can_login_count
            FROM classes c
            LEFT JOIN class_students cs ON cs.class_id = c.id
            LEFT JOIN users u ON u.id = cs.user_id
            LEFT JOIN roles r ON r.id = u.role_id
            WHERE r.code = 'student' OR r.code IS NULL
            GROUP BY c.id, c.name, c.school_year, c.is_active, c.created_at, c.updated_at
            ORDER BY c.school_year DESC, c.name ASC
            "
        );

        return array_map(fn(array $row): array => $this->normalizeClass($row), $rows);
    }

    public function findClassById(int $classId): ?array
    {
        if ($classId <= 0) {
            return null;
        }

        $row = Database::fetchOne(
            "
            SELECT
                c.id,
                c.name,
                c.school_year,
                c.is_active,
                c.created_at,
                c.updated_at,
                COUNT(DISTINCT CASE WHEN r.code = 'student' AND u.is_active = 1 AND u.numero > 0 THEN cs.user_id END) AS students_count,
                COUNT(DISTINCT CASE WHEN r.code = 'student' AND u.is_active = 1 AND u.numero > 0 AND u.can_login = 1 THEN cs.user_id END) AS can_login_count
            FROM classes c
            LEFT JOIN class_students cs ON cs.class_id = c.id
            LEFT JOIN users u ON u.id = cs.user_id
            LEFT JOIN roles r ON r.id = u.role_id
            WHERE c.id = :id
            GROUP BY c.id, c.name, c.school_year, c.is_active, c.created_at, c.updated_at
            LIMIT 1
            ",
            ['id' => $classId]
        );

        return $row ? $this->normalizeClass($row) : null;
    }

    public function getClassStudents(int $classId): array
    {
        if ($classId <= 0) {
            return [];
        }

        $rows = Database::fetchAll(
            "
            SELECT
                u.id,
                u.numero,
                u.code_massar,
                u.nom,
                u.prenom,
                u.nom_ar,
                u.prenom_ar,
                u.can_login,
                u.is_active,
                (
                    SELECT COUNT(*)
                    FROM user_sessions us
                    WHERE us.user_id = u.id
                      AND us.status = 'active'
                ) AS active_sessions
            FROM class_students cs
            INNER JOIN users u ON u.id = cs.user_id
            INNER JOIN roles r ON r.id = u.role_id
            WHERE cs.class_id = :class_id
              AND r.code = 'student'
              AND u.is_active = 1
              AND u.numero > 0
            ORDER BY u.numero ASC, u.nom ASC, u.prenom ASC
            ",
            ['class_id' => $classId]
        );

        return array_map(
            static fn(array $row): array => [
                'id' => (int) $row['id'],
                'numero' => (int) ($row['numero'] ?? 0),
                'code_massar' => (string) ($row['code_massar'] ?? ''),
                'nom' => (string) ($row['nom'] ?? ''),
                'prenom' => (string) ($row['prenom'] ?? ''),
                'display_name' => trim(((string) $row['nom']) . ' ' . ((string) $row['prenom'])),
                'nom_ar' => (string) ($row['nom_ar'] ?? ''),
                'prenom_ar' => (string) ($row['prenom_ar'] ?? ''),
                'can_login' => (bool) ($row['can_login'] ?? false),
                'is_active' => (bool) ($row['is_active'] ?? false),
                'active_sessions' => (int) ($row['active_sessions'] ?? 0),
            ],
            $rows
        );
    }

    public function toggleClassActive(int $classId, bool $active): int
    {
        if ($classId <= 0) {
            return 0;
        }

        return Database::execute(
            "
            UPDATE classes
            SET is_active = :is_active,
                updated_at = NOW()
            WHERE id = :id
            ",
            [
                'id' => $classId,
                'is_active' => $active ? 1 : 0,
            ]
        );
    }

    private function normalizeClass(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'name' => (string) ($row['name'] ?? ''),
            'school_year' => (string) ($row['school_year'] ?? ''),
            'is_active' => (bool) ($row['is_active'] ?? false),
            'students_count' => (int) ($row['students_count'] ?? 0),
            'can_login_count' => (int) ($row['can_login_count'] ?? 0),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
            'label' => trim((string) ($row['name'] ?? '') . ' (' . (string) ($row['school_year'] ?? '') . ')'),
        ];
    }
}