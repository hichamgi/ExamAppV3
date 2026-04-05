<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

class ExamPrintService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::pdo();
    }

    public function getTicketsByExam(int $examId): array
    {
        $sql = "
            SELECT
                ue.id AS user_exam_id,
                ue.exam_id,
                ue.class_id,
                u.id AS user_id,
                u.nom,
                u.prenom,
                u.numero,
                u.code_massar,
                u.secret,
                c.name AS class_name
            FROM user_exams ue
            INNER JOIN users u ON u.id = ue.user_id
            INNER JOIN classes c ON c.id = ue.class_id
            WHERE ue.exam_id = :exam_id
            ORDER BY c.name ASC, u.nom ASC, u.prenom ASC, u.numero ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'exam_id' => $examId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getStudentCopy(int $userExamId): array
    {
        $sql = "
            SELECT
                ue.id,
                ue.exam_id,
                ue.score AS user_exam_score,
                COALESCE(er.final_score, ue.score) AS final_score,
                u.nom,
                u.prenom,
                u.numero,
                u.code_massar,
                c.name AS class_name,
                ua.question_num,
                ua.awarded_points,
                ua.answer_text,
                ua.correct_answer_text,
                ua.question_snapshot
            FROM user_exams ue
            INNER JOIN users u ON u.id = ue.user_id
            INNER JOIN classes c ON c.id = ue.class_id
            LEFT JOIN exam_results er ON er.user_exam_id = ue.id
            INNER JOIN user_answers ua ON ua.user_exam_id = ue.id
            WHERE ue.id = :user_exam_id
            ORDER BY ua.question_num ASC, ua.id ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'user_exam_id' => $userExamId,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$rows) {
            throw new RuntimeException('Copie introuvable.');
        }

        $first = $rows[0];

        $student = [
            'id' => (int) $first['id'],
            'exam_id' => (int) $first['exam_id'],
            'nom' => (string) $first['nom'],
            'prenom' => (string) $first['prenom'],
            'numero' => (int) $first['numero'],
            'code_massar' => (string) $first['code_massar'],
            'class_name' => (string) $first['class_name'],
            'score' => (float) $first['final_score'],
        ];

        $questions = [];
        foreach ($rows as $row) {
            $questions[] = $this->normalizeQuestionRow($row);
        }

        return [
            'student' => $student,
            'questions' => $questions,
        ];
    }

    public function getAllCopies(int $examId, int $classId): array
    {
        $sql = "
            SELECT
                ue.id,
                ue.exam_id,
                ue.class_id,
                ue.score AS user_exam_score,
                COALESCE(er.final_score, ue.score) AS final_score,
                u.nom,
                u.prenom,
                u.numero,
                u.code_massar,
                c.name AS class_name,
                ua.question_num,
                ua.awarded_points,
                ua.answer_text,
                ua.correct_answer_text,
                ua.question_snapshot
            FROM user_exams ue
            INNER JOIN users u ON u.id = ue.user_id
            INNER JOIN classes c ON c.id = ue.class_id
            LEFT JOIN exam_results er ON er.user_exam_id = ue.id
            INNER JOIN user_answers ua ON ua.user_exam_id = ue.id
            WHERE ue.exam_id = :exam_id
              AND ue.class_id = :class_id
            ORDER BY u.nom ASC, u.prenom ASC, u.numero ASC, ua.question_num ASC, ua.id ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'exam_id' => $examId,
            'class_id' => $classId,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $copies = [];

        foreach ($rows as $row) {
            $userExamId = (int) $row['id'];

            if (!isset($copies[$userExamId])) {
                $copies[$userExamId] = [
                    'student' => [
                        'id' => $userExamId,
                        'exam_id' => (int) $row['exam_id'],
                        'nom' => (string) $row['nom'],
                        'prenom' => (string) $row['prenom'],
                        'numero' => (int) $row['numero'],
                        'code_massar' => (string) $row['code_massar'],
                        'class_name' => (string) $row['class_name'],
                        'score' => (float) $row['final_score'],
                    ],
                    'questions' => [],
                ];
            }

            $copies[$userExamId]['questions'][] = $this->normalizeQuestionRow($row);
        }

        return array_values($copies);
    }

    private function normalizeQuestionRow(array $row): array
    {
        $snapshot = [];
        if (!empty($row['question_snapshot'])) {
            $decoded = json_decode((string) $row['question_snapshot'], true);
            if (is_array($decoded)) {
                $snapshot = $decoded;
            }
        }

        $questionText = trim((string) ($snapshot['q'] ?? ''));
        if ($questionText === '') {
            $questionText = '—';
        }

        $studentAnswer = trim((string) ($row['answer_text'] ?? ''));
        $correctAnswer = trim((string) ($row['correct_answer_text'] ?? ''));

        $expectedText = trim((string) ($snapshot['expected_text'] ?? ''));

        if ($expectedText !== '') {
            $correctAnswer = $correctAnswer !== ''
                ? $correctAnswer . "\n" . $expectedText
                : $expectedText;
        }

        return [
            'question_num' => (int) ($row['question_num'] ?? 0),
            'question_text' => $questionText,
            'student_answer' => $studentAnswer,
            'correct_answer' => $correctAnswer,
            'awarded_points' => (float) ($row['awarded_points'] ?? 0),
        ];
    }
}