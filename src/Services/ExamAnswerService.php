<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

class ExamAnswerService
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    public function saveDraft(string $token, array $answers): void
    {
        $sql = "INSERT INTO user_answers_draft
            (attempt_token, question_id, answer_text, updated_at)
            VALUES (:token, :question_id, :answer_text, :updated_at)
            ON DUPLICATE KEY UPDATE
                answer_text = VALUES(answer_text),
                updated_at = VALUES(updated_at)";

        $stmt = $this->pdo->prepare($sql);

        $now = date('Y-m-d H:i:s');

        $stmtCheck = $this->pdo->prepare("
            SELECT id
            FROM questions
            WHERE id = :id
            LIMIT 1
        ");

        foreach ($answers as $questionId => $answer) {
            if (!is_scalar($answer) && !is_null($answer)) {
                continue;
            }

            $stmtCheck->execute([
                ':id' => (int) $questionId,
            ]);

            if (!$stmtCheck->fetch()) {
                continue;
            }

            $stmt->execute([
                ':token' => $token,
                ':question_id' => (int) $questionId,
                ':answer_text' => (string) $answer,
                ':updated_at' => $now,
            ]);
        }
    }

    public function getDraft(string $token): array
    {
        $sql = "SELECT question_id, answer_text
                FROM user_answers_draft
                WHERE attempt_token = :token";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':token' => $token,
        ]);

        $rows = $stmt->fetchAll();
        $result = [];

        foreach ($rows as $row) {
            $result[(string) $row['question_id']] = (string) $row['answer_text'];
        }

        return $result;
    }
}