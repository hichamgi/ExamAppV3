<?php

namespace App\Services;

use App\Core\Database;
use App\Core\SessionManager;

class ExamAttemptService
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function createAttempt(int $userId, int $classId, int $examId, int $durationMinutes): array
    {
        $token = bin2hex(random_bytes(32));

        $now = new \DateTimeImmutable();
        $endsAt = $now->modify("+{$durationMinutes} minutes");

        $sql = "INSERT INTO exam_attempts 
            (attempt_token, user_id, class_id, exam_id, started_at, ends_at, status)
            VALUES (:token, :user_id, :class_id, :exam_id, :started_at, :ends_at, 'in_progress')";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':token' => $token,
            ':user_id' => $userId,
            ':class_id' => $classId,
            ':exam_id' => $examId,
            ':started_at' => $now->format('Y-m-d H:i:s'),
            ':ends_at' => $endsAt->format('Y-m-d H:i:s')
        ]);

        return [
            'attempt_token' => $token,
            'started_at' => $now->format('Y-m-d H:i:s'),
            'ends_at' => $endsAt->format('Y-m-d H:i:s'),
            'server_now' => $now->format('Y-m-d H:i:s')
        ];
    }

    public function validateAttempt(string $token, int $userId): ?array
    {
        $sql = "SELECT * FROM exam_attempts 
                WHERE attempt_token = :token 
                AND user_id = :user_id
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':token' => $token,
            ':user_id' => $userId
        ]);

        $attempt = $stmt->fetch();

        if (!$attempt) {
            return null;
        }

        if ($attempt['status'] === 'submitted' || $attempt['status'] === 'expired') {
            return null;
        }

        return $attempt;
    }

    public function syncAnswers(string $token, int $userId, array $answers): bool
    {
        $attempt = $this->validateAttempt($token, $userId);
        if (!$attempt) {
            return false;
        }

        $now = new \DateTimeImmutable();

        if ($now > new \DateTimeImmutable($attempt['ends_at'])) {
            return false;
        }

        $sql = "UPDATE exam_attempts 
                SET last_sync_at = :sync_at,
                    status = 'in_progress'
                WHERE attempt_token = :token";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':sync_at' => $now->format('Y-m-d H:i:s'),
            ':token' => $token
        ]);
    }

    public function submitFinal(string $token, int $userId, array $snapshot): bool
    {
        $attempt = $this->validateAttempt($token, $userId);
        if (!$attempt) {
            return false;
        }

        if (($snapshot['locked'] ?? false) !== true) {
            return false;
        }

        $finalizedAt = new \DateTimeImmutable($snapshot['finalized_at_client']);
        $endsAt = new \DateTimeImmutable($attempt['ends_at']);

        // fenêtre de tolérance 3 min
        $graceLimit = $endsAt->modify('+3 minutes');

        $now = new \DateTimeImmutable();

        if ($finalizedAt > $endsAt) {
            return false;
        }

        if ($now > $graceLimit) {
            return false;
        }

        $sql = "UPDATE exam_attempts 
                SET status = 'submitted',
                    snapshot = :snapshot,
                    submitted_at = :submitted_at
                WHERE attempt_token = :token";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':snapshot' => json_encode($snapshot),
            ':submitted_at' => $now->format('Y-m-d H:i:s'),
            ':token' => $token
        ]);
    }
}