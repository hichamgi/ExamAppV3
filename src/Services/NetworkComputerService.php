<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Request;

final class NetworkComputerService
{
    public function resolveClientComputer(Request $request): array
    {
        $ip = $request->ip();

        if ($ip === '') {
            return [
                'allowed' => false,
                'ip' => '',
                'network_type' => 'unknown',
                'computer' => null,
                'reason' => 'Adresse IP introuvable.',
            ];
        }

        $computer = $this->findByIp($ip);

        if ($computer === null) {
            return [
                'allowed' => false,
                'ip' => $ip,
                'network_type' => 'unknown',
                'computer' => null,
                'reason' => 'Poste non autorisé.',
            ];
        }

        if (!(bool) $computer['is_active']) {
            return [
                'allowed' => false,
                'ip' => $ip,
                'network_type' => $this->detectNetworkType($computer, $ip),
                'computer' => $computer,
                'reason' => 'Poste désactivé.',
            ];
        }

        return [
            'allowed' => true,
            'ip' => $ip,
            'network_type' => $this->detectNetworkType($computer, $ip),
            'computer' => $computer,
            'reason' => null,
        ];
    }

    public function resolveAllowedComputerOrFail(Request $request): array
    {
        $resolved = $this->resolveClientComputer($request);

        if (!(bool) $resolved['allowed']) {
            return [
                'success' => false,
                'status' => 403,
                'message' => (string) ($resolved['reason'] ?? 'Poste non autorisé.'),
                'computer_id' => null,
                'computer' => $resolved['computer'],
                'ip' => (string) ($resolved['ip'] ?? ''),
                'network_type' => (string) ($resolved['network_type'] ?? 'unknown'),
            ];
        }

        $computer = is_array($resolved['computer']) ? $resolved['computer'] : null;

        return [
            'success' => true,
            'status' => 200,
            'message' => 'Poste autorisé.',
            'computer_id' => $computer !== null ? (int) ($computer['id'] ?? 0) : null,
            'computer' => $computer,
            'ip' => (string) ($resolved['ip'] ?? ''),
            'network_type' => (string) ($resolved['network_type'] ?? 'unknown'),
        ];
    }

    public function findByIp(string $ip): ?array
    {
        if ($ip === '') {
            return null;
        }

        $row = Database::fetchOne(
            "SELECT
                id,
                name,
                hostname,
                ip_lan,
                ip_wifi,
                is_active,
                room_name,
                description,
                created_at,
                updated_at
            FROM lab_computers
            WHERE ip_lan = :ip_lan OR ip_wifi = :ip_wifi
            LIMIT 1",
            [
                'ip_lan' => $ip,
                'ip_wifi' => $ip,
            ]
        );

        return $row ? $this->normalizeComputer($row) : null;
    }

    public function findById(int $computerId): ?array
    {
        if ($computerId <= 0) {
            return null;
        }

        $row = Database::fetchOne(
            "SELECT
                id,
                name,
                hostname,
                ip_lan,
                ip_wifi,
                is_active,
                room_name,
                description,
                created_at,
                updated_at
             FROM lab_computers
             WHERE id = :id
             LIMIT 1",
            ['id' => $computerId]
        );

        return $row ? $this->normalizeComputer($row) : null;
    }

    public function listActiveComputers(?string $roomName = null): array
    {
        $sql = "SELECT
                    id,
                    name,
                    hostname,
                    ip_lan,
                    ip_wifi,
                    is_active,
                    room_name,
                    description,
                    created_at,
                    updated_at
                FROM lab_computers
                WHERE is_active = 1";
        $params = [];

        if ($roomName !== null && trim($roomName) !== '') {
            $sql .= " AND room_name = :room_name";
            $params['room_name'] = trim($roomName);
        }

        $sql .= " ORDER BY name ASC";

        $rows = Database::fetchAll($sql, $params);

        return array_map(fn(array $row): array => $this->normalizeComputer($row), $rows);
    }

    public function listAllComputers(?string $roomName = null): array
    {
        $sql = "SELECT
                    id,
                    name,
                    hostname,
                    ip_lan,
                    ip_wifi,
                    is_active,
                    room_name,
                    description,
                    created_at,
                    updated_at
                FROM lab_computers
                WHERE 1 = 1";
        $params = [];

        if ($roomName !== null && trim($roomName) !== '') {
            $sql .= " AND room_name = :room_name";
            $params['room_name'] = trim($roomName);
        }

        $sql .= " ORDER BY is_active DESC, name ASC";

        $rows = Database::fetchAll($sql, $params);

        return array_map(fn(array $row): array => $this->normalizeComputer($row), $rows);
    }

    public function detectNetworkType(array $computer, string $ip): string
    {
        $ipLan = (string) ($computer['ip_lan'] ?? '');
        $ipWifi = (string) ($computer['ip_wifi'] ?? '');

        if ($ip !== '' && $ipLan !== '' && hash_equals($ipLan, $ip)) {
            return 'lan';
        }

        if ($ip !== '' && $ipWifi !== '' && hash_equals($ipWifi, $ip)) {
            return 'wifi';
        }

        return 'unknown';
    }

    public function isAllowedStudentComputer(Request $request): bool
    {
        return (bool) ($this->resolveClientComputer($request)['allowed'] ?? false);
    }

    public function isExpectedComputerForSession(int $computerId, string $ip): bool
    {
        $computer = $this->findById($computerId);

        if ($computer === null || !(bool) $computer['is_active']) {
            return false;
        }

        return $this->detectNetworkType($computer, $ip) !== 'unknown';
    }

    public function buildConventionSeedData(
        string $roomName = 'Salle Info',
        int $studentCount = 20,
        int $startOffset = 200
    ): array {
        $data = [];

        $data[] = [
            'name' => 'posteprof',
            'hostname' => 'posteprof',
            'ip_lan' => '192.168.100.200',
            'ip_wifi' => '192.168.100.201',
            'is_active' => 1,
            'room_name' => $roomName,
            'description' => 'Poste professeur',
        ];

        for ($i = 1; $i <= $studentCount; $i++) {
            $offset = $i * 2;
            $ipLanLast = $startOffset + $offset;
            $ipWifiLast = $ipLanLast + 1;

            $name = 'poste' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);

            $data[] = [
                'name' => $name,
                'hostname' => $name,
                'ip_lan' => '192.168.100.' . $ipLanLast,
                'ip_wifi' => '192.168.100.' . $ipWifiLast,
                'is_active' => 1,
                'room_name' => $roomName,
                'description' => 'Poste élève ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            ];
        }

        return $data;
    }

    private function normalizeComputer(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'hostname' => (string) ($row['hostname'] ?? ''),
            'ip_lan' => (string) ($row['ip_lan'] ?? ''),
            'ip_wifi' => (string) ($row['ip_wifi'] ?? ''),
            'is_active' => (bool) ($row['is_active'] ?? false),
            'room_name' => (string) ($row['room_name'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }
}