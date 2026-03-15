<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class ExamAdminService
{
    public function listExams(): array
    {
        $rows = Database::fetchAll(
            "
            SELECT
                e.id,
                e.code,
                e.title,
                e.duration_minutes,
                e.is_active,
                e.allow_print,
                e.created_at,
                e.updated_at,
                COUNT(DISTINCT q.id) AS questions_count,
                COUNT(DISTINCT ue.id) AS participants_count
            FROM exams e
            LEFT JOIN questions q ON q.exam_id = e.id
            LEFT JOIN user_exams ue ON ue.exam_id = e.id
            GROUP BY e.id, e.code, e.title, e.duration_minutes, e.is_active, e.allow_print, e.created_at, e.updated_at
            ORDER BY e.id DESC
            "
        );

        return array_map(fn(array $row): array => $this->normalizeExam($row), $rows);
    }

    public function findExamById(int $examId): ?array
    {
        if ($examId <= 0) {
            return null;
        }

        $row = Database::fetchOne(
            "
            SELECT
                e.id,
                e.code,
                e.title,
                e.duration_minutes,
                e.is_active,
                e.allow_print,
                e.metadata,
                e.created_at,
                e.updated_at,
                COUNT(DISTINCT q.id) AS questions_count,
                COUNT(DISTINCT ue.id) AS participants_count
            FROM exams e
            LEFT JOIN questions q ON q.exam_id = e.id
            LEFT JOIN user_exams ue ON ue.exam_id = e.id
            WHERE e.id = :id
            GROUP BY e.id, e.code, e.title, e.duration_minutes, e.is_active, e.allow_print, e.metadata, e.created_at, e.updated_at
            LIMIT 1
            ",
            ['id' => $examId]
        );

        return $row ? $this->normalizeExam($row) : null;
    }

    public function toggleExamActive(int $examId, bool $active): int
    {
        if ($examId <= 0) {
            return 0;
        }

        return Database::execute(
            "
            UPDATE exams
            SET is_active = :is_active,
                updated_at = NOW()
            WHERE id = :id
            ",
            [
                'id' => $examId,
                'is_active' => $active ? 1 : 0,
            ]
        );
    }

    public function toggleExamPrint(int $examId, bool $allowPrint): int
    {
        if ($examId <= 0) {
            return 0;
        }

        return Database::execute(
            "
            UPDATE exams
            SET allow_print = :allow_print,
                updated_at = NOW()
            WHERE id = :id
            ",
            [
                'id' => $examId,
                'allow_print' => $allowPrint ? 1 : 0,
            ]
        );
    }

    public function getExamResults(int $examId, ?int $classId = null): array
    {
        $where = ["ue.exam_id = :exam_id"];
        $params = ['exam_id' => $examId];

        if ($classId !== null && $classId > 0) {
            $where[] = "ue.class_id = :class_id";
            $params['class_id'] = $classId;
        }

        $whereSql = implode(' AND ', $where);

        $rows = Database::fetchAll(
            "
            SELECT
                ue.id AS user_exam_id,
                ue.user_id,
                ue.class_id,
                ue.is_absent,
                ue.is_retake,
                ue.score,
                ue.started_at,
                ue.submitted_at,
                ue.duration_seconds,
                ue.status,
                u.numero,
                u.code_massar,
                u.nom,
                u.prenom,
                c.name AS class_name,
                er.total_questions,
                er.answered_questions,
                er.correct_questions,
                er.wrong_questions,
                er.blank_questions,
                er.final_score
            FROM user_exams ue
            INNER JOIN users u ON u.id = ue.user_id
            INNER JOIN classes c ON c.id = ue.class_id
            LEFT JOIN exam_results er ON er.user_exam_id = ue.id
            WHERE {$whereSql}
            ORDER BY c.name ASC, u.numero ASC, u.nom ASC
            ",
            $params
        );

        return array_map(
            static fn(array $row): array => [
                'user_exam_id' => (int) $row['user_exam_id'],
                'user_id' => (int) $row['user_id'],
                'class_id' => (int) $row['class_id'],
                'is_absent' => (bool) $row['is_absent'],
                'is_retake' => (bool) $row['is_retake'],
                'score' => (float) $row['score'],
                'started_at' => (string) ($row['started_at'] ?? ''),
                'submitted_at' => (string) ($row['submitted_at'] ?? ''),
                'duration_seconds' => (int) $row['duration_seconds'],
                'status' => (string) $row['status'],
                'numero' => (int) ($row['numero'] ?? 0),
                'code_massar' => (string) ($row['code_massar'] ?? ''),
                'student_name' => trim(((string) $row['nom']) . ' ' . ((string) $row['prenom'])),
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

    private function normalizeExam(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'code' => (string) ($row['code'] ?? ''),
            'title' => (string) ($row['title'] ?? ''),
            'duration_minutes' => (int) ($row['duration_minutes'] ?? 0),
            'is_active' => (bool) ($row['is_active'] ?? false),
            'allow_print' => (bool) ($row['allow_print'] ?? false),
            'metadata' => (string) ($row['metadata'] ?? ''),
            'questions_count' => (int) ($row['questions_count'] ?? 0),
            'participants_count' => (int) ($row['participants_count'] ?? 0),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }
}