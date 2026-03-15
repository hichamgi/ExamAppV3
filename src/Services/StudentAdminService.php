<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\SessionManager;

final class StudentAdminService
{
    public function paginateStudents(
        string $search = '',
        ?int $classId = null,
        ?int $canLogin = null,
        ?int $isActive = null,
        int $page = 1,
        int $perPage = 25
    ): array {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = ["r.code = 'student'"];
        $params = [];

        if ($search !== '') {
            $where[] = "(
                u.code_massar LIKE :search
                OR u.nom LIKE :search
                OR u.prenom LIKE :search
                OR CONCAT(u.nom, ' ', u.prenom) LIKE :search
            )";
            $params['search'] = '%' . $search . '%';
        }

        if ($classId !== null && $classId > 0) {
            $where[] = "cs.class_id = :class_id";
            $params['class_id'] = $classId;
        }

        if ($canLogin !== null) {
            $where[] = "u.can_login = :can_login";
            $params['can_login'] = $canLogin;
        }

        if ($isActive !== null) {
            $where[] = "u.is_active = :is_active";
            $params['is_active'] = $isActive;
        }

        $whereSql = implode(' AND ', $where);

        $countSql = "
            SELECT COUNT(DISTINCT u.id)
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            LEFT JOIN class_students cs ON cs.user_id = u.id
            WHERE {$whereSql}
        ";

        $total = (int) Database::fetchValue($countSql, $params, 0);

        $sql = "
            SELECT
                u.id,
                u.numero,
                u.code_massar,
                u.can_login,
                u.is_active,
                u.nom,
                u.prenom,
                u.nom_ar,
                u.prenom_ar,
                u.last_login_at,
                c.id AS class_id,
                c.name AS class_name,
                c.school_year,
                (
                    SELECT COUNT(*)
                    FROM user_sessions us
                    WHERE us.user_id = u.id
                      AND us.status = 'active'
                ) AS active_sessions
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            LEFT JOIN class_students cs ON cs.user_id = u.id
            LEFT JOIN classes c ON c.id = cs.class_id
            WHERE {$whereSql}
            ORDER BY c.name ASC, u.numero ASC, u.nom ASC, u.prenom ASC
            LIMIT {$perPage} OFFSET {$offset}
        ";

        $rows = Database::fetchAll($sql, $params);

        return [
            'items' => array_map(fn(array $row): array => $this->normalizeStudent($row), $rows),
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    public function findStudentById(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $row = Database::fetchOne(
            "
            SELECT
                u.id,
                u.numero,
                u.code_massar,
                u.can_login,
                u.is_active,
                u.nom,
                u.prenom,
                u.nom_ar,
                u.prenom_ar,
                u.last_login_at,
                c.id AS class_id,
                c.name AS class_name,
                c.school_year
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            LEFT JOIN class_students cs ON cs.user_id = u.id
            LEFT JOIN classes c ON c.id = cs.class_id
            WHERE u.id = :id
              AND r.code = 'student'
            LIMIT 1
            ",
            ['id' => $userId]
        );

        return $row ? $this->normalizeStudent($row) : null;
    }

    public function toggleStudentActive(int $userId, bool $active): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $affected = Database::execute(
            "
            UPDATE users
            SET is_active = :is_active,
                can_login = CASE WHEN :is_active = 0 THEN 0 ELSE can_login END,
                updated_at = NOW()
            WHERE id = :id
            ",
            [
                'id' => $userId,
                'is_active' => $active ? 1 : 0,
            ]
        );

        if (!$active) {
            $this->forceLogoutStudent($userId);
        }

        return $affected;
    }

    public function toggleStudentCanLogin(int $userId, bool $canLogin): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $affected = Database::execute(
            "
            UPDATE users
            SET can_login = :can_login,
                updated_at = NOW()
            WHERE id = :id
            ",
            [
                'id' => $userId,
                'can_login' => $canLogin ? 1 : 0,
            ]
        );

        if (!$canLogin) {
            $this->forceLogoutStudent($userId);
        }

        return $affected;
    }

    public function forceLogoutStudent(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        return Database::execute(
            "
            UPDATE user_sessions
            SET status = 'closed',
                closed_at = NOW(),
                updated_at = NOW()
            WHERE user_id = :user_id
              AND status = 'active'
            ",
            ['user_id' => $userId]
        );
    }

    public function getStudentExamHistory(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $rows = Database::fetchAll(
            "
            SELECT
                ue.id,
                ue.exam_id,
                ue.class_id,
                ue.is_absent,
                ue.is_retake,
                ue.score,
                ue.started_at,
                ue.submitted_at,
                ue.duration_seconds,
                ue.status,
                e.code AS exam_code,
                e.title AS exam_title,
                c.name AS class_name,
                er.total_questions,
                er.answered_questions,
                er.correct_questions,
                er.wrong_questions,
                er.blank_questions,
                er.final_score
            FROM user_exams ue
            INNER JOIN exams e ON e.id = ue.exam_id
            INNER JOIN classes c ON c.id = ue.class_id
            LEFT JOIN exam_results er ON er.user_exam_id = ue.id
            WHERE ue.user_id = :user_id
            ORDER BY ue.id DESC
            ",
            ['user_id' => $userId]
        );

        return array_map(
            static fn(array $row): array => [
                'id' => (int) $row['id'],
                'exam_id' => (int) $row['exam_id'],
                'class_id' => (int) $row['class_id'],
                'is_absent' => (bool) $row['is_absent'],
                'is_retake' => (bool) $row['is_retake'],
                'score' => (float) $row['score'],
                'started_at' => (string) ($row['started_at'] ?? ''),
                'submitted_at' => (string) ($row['submitted_at'] ?? ''),
                'duration_seconds' => (int) $row['duration_seconds'],
                'status' => (string) $row['status'],
                'exam_code' => (string) $row['exam_code'],
                'exam_title' => (string) $row['exam_title'],
                'class_name' => (string) $row['class_name'],
                'total_questions' => (int) ($row['total_questions'] ?? 0),
                'answered_questions' => (int) ($row['answered_questions'] ?? 0),
                'correct_questions' => (int) ($row['correct_questions'] ?? 0),
                'wrong_questions' => (int) ($row['wrong_questions'] ?? 0),
                'blank_questions' => (int) ($row['blank_questions'] ?? 0),
                'final_score' => (float) ($row['final_score'] ?? 0),
            ],
            $rows
        );
    }

    private function normalizeStudent(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'numero' => (int) ($row['numero'] ?? 0),
            'code_massar' => (string) ($row['code_massar'] ?? ''),
            'can_login' => (bool) ($row['can_login'] ?? false),
            'is_active' => (bool) ($row['is_active'] ?? false),
            'nom' => (string) ($row['nom'] ?? ''),
            'prenom' => (string) ($row['prenom'] ?? ''),
            'nom_ar' => (string) ($row['nom_ar'] ?? ''),
            'prenom_ar' => (string) ($row['prenom_ar'] ?? ''),
            'display_name' => trim(((string) ($row['nom'] ?? '')) . ' ' . ((string) ($row['prenom'] ?? ''))),
            'class_id' => isset($row['class_id']) ? (int) $row['class_id'] : null,
            'class_name' => (string) ($row['class_name'] ?? ''),
            'school_year' => (string) ($row['school_year'] ?? ''),
            'last_login_at' => (string) ($row['last_login_at'] ?? ''),
            'active_sessions' => (int) ($row['active_sessions'] ?? 0),
        ];
    }
}