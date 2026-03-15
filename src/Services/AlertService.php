<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class AlertService
{
    public function createConcurrentLoginAlert(
        array $user,
        int $classId,
        array $existingSession,
        array $attemptedComputer,
        string $attemptedIp,
        string $attemptedNetworkType,
        ?string $notes = null
    ): int {
        $userId = (int) ($user['id'] ?? 0);

        return Database::insert('login_attempt_alerts', [
            'user_id' => $userId > 0 ? $userId : null,
            'username_attempted' => (string) ($user['code_massar'] ?? ''),
            'class_id' => $classId > 0 ? $classId : null,
            'existing_session_id' => isset($existingSession['id']) ? (int) $existingSession['id'] : null,
            'existing_computer_id' => isset($existingSession['computer_id']) ? (int) $existingSession['computer_id'] : null,
            'existing_ip' => (string) ($existingSession['ip_address'] ?? ''),
            'attempted_computer_id' => isset($attemptedComputer['id']) ? (int) $attemptedComputer['id'] : null,
            'attempted_ip' => $attemptedIp,
            'attempted_network_type' => $attemptedNetworkType !== '' ? $attemptedNetworkType : 'unknown',
            'attempted_at' => date('Y-m-d H:i:s'),
            'status' => 'refused',
            'notes' => $notes,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function updateStatus(int $alertId, string $status, ?string $notes = null): int
    {
        $allowed = ['refused', 'suspect', 'validated', 'ignored'];

        if ($alertId <= 0 || !in_array($status, $allowed, true)) {
            return 0;
        }

        return Database::execute(
            "UPDATE login_attempt_alerts
             SET status = :status,
                 notes = :notes
             WHERE id = :id",
            [
                'status' => $status,
                'notes' => $notes,
                'id' => $alertId,
            ]
        );
    }

    public function markAsValidated(int $alertId, ?string $notes = null): int
    {
        return $this->updateStatus($alertId, 'validated', $notes);
    }

    public function markAsIgnored(int $alertId, ?string $notes = null): int
    {
        return $this->updateStatus($alertId, 'ignored', $notes);
    }

    public function markAsSuspect(int $alertId, ?string $notes = null): int
    {
        return $this->updateStatus($alertId, 'suspect', $notes);
    }

    public function findById(int $alertId): ?array
    {
        if ($alertId <= 0) {
            return null;
        }

        return Database::fetchOne(
            "SELECT *
             FROM login_attempt_alerts
             WHERE id = :id
             LIMIT 1",
            ['id' => $alertId]
        );
    }
}