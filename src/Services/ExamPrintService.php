<?php

namespace App\Services;

use PDO;

class ExamPrintService
{
    public function __construct(private PDO $pdo) {}

    public function getTicketsByExam(int $examId): array
    {
        $sql = "
            SELECT 
                ue.id,
                u.nom,
                u.prenom,
                c.name AS class_name,
                u.numero,
                u.login,
                u.plain_password
            FROM user_exams ue
            JOIN users u ON u.id = ue.user_id
            LEFT JOIN classes c ON c.id = ue.class_id
            WHERE ue.exam_id = :exam_id
            ORDER BY c.name, u.nom, u.prenom
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['exam_id' => $examId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStudentCopy(int $userExamId): array
    {
        $sql = "
            SELECT 
                ue.id,
                ue.exam_id,
                u.nom,
                u.prenom,
                c.name AS class_name,
                COALESCE(er.final_score, ue.score) AS score,
                ua.question_snapshot AS snapshot
            FROM user_exams ue
            JOIN users u ON u.id = ue.user_id
            JOIN classes c ON c.id = ue.class_id
            LEFT JOIN exam_results er ON er.user_exam_id = ue.id
            JOIN user_answers ua ON ua.user_exam_id = ue.id
            WHERE ue.id = :id
            ORDER BY ua.question_num, ua.id
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $userExamId]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$rows) {
            throw new \RuntimeException('Copie introuvable');
        }

        $student = [
            'id' => (int) $rows[0]['id'],
            'exam_id' => (int) $rows[0]['exam_id'],
            'nom' => (string) $rows[0]['nom'],
            'prenom' => (string) $rows[0]['prenom'],
            'class_name' => (string) $rows[0]['class_name'],
            'score' => (float) $rows[0]['score'],
        ];

        $questions = [];

        foreach ($rows as $row) {
            $snapshotRaw = $row['snapshot'] ?? null;
            if ($snapshotRaw === null || $snapshotRaw === '') {
                continue;
            }

            $snapshot = json_decode((string) $snapshotRaw, true);

            if (is_array($snapshot)) {
                $questions[] = $snapshot;
            }
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
                u.nom,
                u.prenom,
                c.name AS class_name,
                COALESCE(er.final_score, ue.score) AS score,
                ua.question_snapshot AS snapshot
            FROM user_exams ue
            JOIN users u ON u.id = ue.user_id
            JOIN classes c ON c.id = ue.class_id
            LEFT JOIN exam_results er ON er.user_exam_id = ue.id
            JOIN user_answers ua ON ua.user_exam_id = ue.id
            WHERE ue.exam_id = :exam_id
            AND ue.class_id = :class_id
            ORDER BY c.name, u.nom, u.prenom, ua.question_num, ua.id
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'exam_id' => $examId,
            'class_id' => $classId,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                        'class_name' => (string) $row['class_name'],
                        'score' => (float) $row['score'],
                    ],
                    'questions' => [],
                ];
            }

            $snapshotRaw = $row['snapshot'] ?? null;
            if ($snapshotRaw === null || $snapshotRaw === '') {
                continue;
            }

            $snapshot = json_decode((string) $snapshotRaw, true);

            if (is_array($snapshot)) {
                $copies[$userExamId]['questions'][] = $snapshot;
            }
        }

        return array_values($copies);
    }
}