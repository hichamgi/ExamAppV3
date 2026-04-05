<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\SessionManager;

class ExamAttemptService
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    public function createAttempt(int $userId, int $classId, int $examId, int $durationMinutes): array
    {
        $token = bin2hex(random_bytes(32));

        $now = new \DateTimeImmutable();
        $endsAt = $now->modify("+{$durationMinutes} minutes");

        $sql = "INSERT INTO exam_attempts
            (attempt_token, user_id, class_id, exam_id, started_at, ends_at, session_token)
            VALUES (:token, :user_id, :class_id, :exam_id, :started_at, :ends_at, :session_token)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':token' => $token,
            ':user_id' => $userId,
            ':class_id' => $classId,
            ':exam_id' => $examId,
            ':started_at' => $now->format('Y-m-d H:i:s'),
            ':ends_at' => $endsAt->format('Y-m-d H:i:s'),
            ':session_token' => hash('sha256', (string) SessionManager::sessionToken()),
        ]);

        return [
            'attempt_token' => $token,
            'started_at' => $now->format('Y-m-d H:i:s'),
            'ends_at' => $endsAt->format('Y-m-d H:i:s'),
            'server_now' => $now->format('Y-m-d H:i:s'),
        ];
    }

    public function validateAttempt(string $token, int $userId): ?array
    {
        $sql = "SELECT *
                FROM exam_attempts
                WHERE attempt_token = :token
                  AND user_id = :user_id
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':token' => $token,
            ':user_id' => $userId,
        ]);

        $attempt = $stmt->fetch();

        if (!$attempt) {
            return null;
        }

        $currentSessionToken = SessionManager::sessionToken();

        if ($currentSessionToken === null || $currentSessionToken === '') {
            return null;
        }

        if ((string) ($attempt['session_token'] ?? '') !== hash('sha256', $currentSessionToken)) {
            return null;
        }

        if (($attempt['status'] ?? null) === 'submitted' || ($attempt['status'] ?? null) === 'expired') {
            return null;
        }

        if (($attempt['locked_at'] ?? null) !== null) {
            return null;
        }

        return $attempt;
    }

    public function sync(string $token, int $userId, array $answers, ExamAnswerService $answerService): bool
    {
        $attempt = $this->validateAttempt($token, $userId);

        if (!$attempt) {
            return false;
        }

        $now = new \DateTimeImmutable();

        if ($now > new \DateTimeImmutable((string) $attempt['ends_at'])) {
            return false;
        }

        $answerService->saveDraft($token, $answers);

        if (!empty($attempt['last_sync_at'])) {
            $lastSync = new \DateTimeImmutable((string) $attempt['last_sync_at']);
            if (($now->getTimestamp() - $lastSync->getTimestamp()) < 2) {
                return true;
            }
        }

        $sql = "UPDATE exam_attempts
                SET last_sync_at = :sync_at
                WHERE attempt_token = :token";

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            ':sync_at' => $now->format('Y-m-d H:i:s'),
            ':token' => $token,
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

        if (($attempt['status'] ?? null) === 'submitted') {
            return false;
        }

        if (!isset($snapshot['finalized_at_client'], $snapshot['answers']) || !is_array($snapshot['answers'])) {
            return false;
        }

        $finalizedAt = new \DateTimeImmutable((string) $snapshot['finalized_at_client']);
        $endsAt = new \DateTimeImmutable((string) $attempt['ends_at']);
        $graceLimit = $endsAt->modify('+3 minutes');
        $now = new \DateTimeImmutable();

        if ($finalizedAt > $endsAt) {
            return false;
        }

        if ($now > $graceLimit) {
            return false;
        }

        if (count($snapshot['answers']) > 200) {
            return false;
        }

        $serverHash = hash('sha256', json_encode($snapshot['answers'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        if (($snapshot['hash'] ?? '') !== $serverHash) {
            return false;
        }

        $sql = "UPDATE exam_attempts
                SET status = 'submitted',
                    snapshot = :snapshot,
                    submitted_at = :submitted_at,
                    locked_at = :locked_at
                WHERE attempt_token = :token";

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            ':snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':submitted_at' => $now->format('Y-m-d H:i:s'),
            ':locked_at' => $now->format('Y-m-d H:i:s'),
            ':token' => $token,
        ]);
    }
}