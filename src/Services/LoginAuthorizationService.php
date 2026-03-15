<?php

namespace App\Services;

use App\Core\Database;

class LoginAuthorizationService
{
    public function allowStudentLogin(int $userId): int
    {
        return $this->setStudentLogin($userId, true);
    }

    public function denyStudentLogin(int $userId): int
    {
        return $this->setStudentLogin($userId, false);
    }

    public function allowClassLogin(int $classId): int
    {
        return $this->setClassLogin($classId, true);
    }

    public function denyClassLogin(int $classId): int
    {
        return $this->setClassLogin($classId, false);
    }

    public function allowGroupLogin(int $classId, int $group): int
    {
        if ($classId <= 0 || !in_array($group, [1, 2], true)) {
            return 0;
        }

        $students = Database::fetchAll(
            "
            SELECT u.id
            FROM class_students cs
            INNER JOIN users u ON u.id = cs.user_id
            INNER JOIN roles r ON r.id = u.role_id
            WHERE cs.class_id = :class_id
              AND r.code = 'student'
              AND u.is_active = 1
              AND u.numero > 0
            ORDER BY u.numero ASC, u.id ASC
            ",
            ['class_id' => $classId]
        );

        if ($students === []) {
            Database::execute(
                "
                UPDATE users u
                INNER JOIN class_students cs ON cs.user_id = u.id
                SET u.can_login = 0,
                    u.updated_at = NOW()
                WHERE cs.class_id = :class_id
                ",
                ['class_id' => $classId]
            );

            $this->closeUnauthorizedSessionsByClass($classId);

            return 0;
        }

        $ids = array_map(static fn(array $row): int => (int) $row['id'], $students);
        $count = count($ids);
        $half = (int) ceil($count / 2);

        $allowedIds = $group === 1
            ? array_slice($ids, 0, $half)
            : array_slice($ids, $half);

        Database::transaction(function () use ($classId, $allowedIds): void {
            Database::execute(
                "
                UPDATE users u
                INNER JOIN class_students cs ON cs.user_id = u.id
                SET u.can_login = 0,
                    u.updated_at = NOW()
                WHERE cs.class_id = :class_id
                ",
                ['class_id' => $classId]
            );

            if ($allowedIds !== []) {
                $placeholders = [];
                $params = [];

                foreach ($allowedIds as $index => $id) {
                    $key = 'id_' . $index;
                    $placeholders[] = ':' . $key;
                    $params[$key] = $id;
                }

                $sql = "
                    UPDATE users
                    SET can_login = 1,
                        updated_at = NOW()
                    WHERE id IN (" . implode(', ', $placeholders) . ")
                ";

                Database::execute($sql, $params);
            }
        });

        $this->closeUnauthorizedSessionsByClass($classId);

        return count($allowedIds);
    }

    public function denyAllClassLogins(int $classId): int
    {
        return $this->denyClassLogin($classId);
    }

    private function setStudentLogin(int $userId, bool $allowed): int
    {
        if ($userId <= 0) {
            return 0;
        }

        if ($allowed) {
            $affected = Database::execute(
                "
                UPDATE users
                SET can_login = 1,
                    updated_at = NOW()
                WHERE id = :id
                  AND is_active = 1
                  AND numero > 0
                ",
                ['id' => $userId]
            );
        } else {
            $affected = Database::execute(
                "
                UPDATE users
                SET can_login = 0,
                    updated_at = NOW()
                WHERE id = :id
                ",
                ['id' => $userId]
            );

            Database::execute(
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

        return $affected;
    }

    private function setClassLogin(int $classId, bool $allowed): int
    {
        if ($classId <= 0) {
            return 0;
        }

        if (!$allowed) {
            $affected = Database::execute(
                "
                UPDATE users u
                INNER JOIN class_students cs ON cs.user_id = u.id
                SET u.can_login = 0,
                    u.updated_at = NOW()
                WHERE cs.class_id = :class_id
                ",
                ['class_id' => $classId]
            );

            $this->closeUnauthorizedSessionsByClass($classId);

            return $affected;
        }

        Database::transaction(function () use ($classId): void {
            Database::execute(
                "
                UPDATE users u
                INNER JOIN class_students cs ON cs.user_id = u.id
                SET u.can_login = 0,
                    u.updated_at = NOW()
                WHERE cs.class_id = :class_id
                ",
                ['class_id' => $classId]
            );

            Database::execute(
                "
                UPDATE users u
                INNER JOIN class_students cs ON cs.user_id = u.id
                INNER JOIN roles r ON r.id = u.role_id
                SET u.can_login = 1,
                    u.updated_at = NOW()
                WHERE cs.class_id = :class_id
                  AND r.code = 'student'
                  AND u.is_active = 1
                  AND u.numero > 0
                ",
                ['class_id' => $classId]
            );
        });

        return Database::fetchValue(
            "
            SELECT COUNT(*)
            FROM class_students cs
            INNER JOIN users u ON u.id = cs.user_id
            INNER JOIN roles r ON r.id = u.role_id
            WHERE cs.class_id = :class_id
              AND r.code = 'student'
              AND u.is_active = 1
              AND u.numero > 0
              AND u.can_login = 1
            ",
            ['class_id' => $classId],
            0
        );
    }

    private function closeUnauthorizedSessionsByClass(int $classId): void
    {
        Database::execute(
            "
            UPDATE user_sessions us
            INNER JOIN users u ON u.id = us.user_id
            INNER JOIN class_students cs ON cs.user_id = u.id
            SET us.status = 'closed',
                us.closed_at = NOW(),
                us.updated_at = NOW()
            WHERE cs.class_id = :class_id
              AND us.status = 'active'
              AND u.can_login = 0
            ",
            ['class_id' => $classId]
        );
    }
}