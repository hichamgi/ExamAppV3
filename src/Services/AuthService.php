<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\SessionManager;
use RuntimeException;

final class AuthService
{
    private NetworkComputerService $networkComputerService;
    private AlertService $alertService;

    public function __construct()
    {
        $this->networkComputerService = new NetworkComputerService();
        $this->alertService = new AlertService();
    }

    public function loginAdmin(string $identifier, string $password, Request $request): array
    {
        $identifier = trim($identifier);

        if ($identifier === '' || $password === '') {
            return $this->fail('Identifiants invalides.', 422, 'invalid_credentials');
        }

        $user = Database::fetchOne(
            "SELECT
                u.id,
                u.role_id,
                r.code AS role_code,
                u.numero,
                u.code_massar,
                u.password_hash,
                u.can_login,
                u.is_active,
                u.nom,
                u.prenom,
                u.nom_ar,
                u.prenom_ar
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE r.code = 'admin'
               AND (
                    u.code_massar = :identifier
                    OR CONCAT(u.nom, ' ', u.prenom) = :identifier_name
               )
             LIMIT 1",
            [
                'identifier' => $identifier,
                'identifier_name' => $identifier,
            ]
        );

        if (!$user) {
            return $this->fail('Identifiants invalides.', 401, 'invalid_credentials');
        }

        if (!(bool) $user['is_active']) {
            return $this->fail('Compte désactivé.', 403, 'account_disabled');
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            return $this->fail('Identifiants invalides.', 401, 'invalid_credentials');
        }

        SessionManager::login([
            'user_id' => (int) $user['id'],
            'role' => (string) $user['role_code'],
            'role_id' => (int) $user['role_id'],
            'class_id' => null,
            'display_name' => trim(((string) $user['nom']) . ' ' . ((string) $user['prenom'])),
        ], [
            'user_id' => (int) $user['id'],
            'class_id' => null,
            'computer_id' => null,
            'network_type' => 'unknown',
        ]);

        $this->updateLastLogin((int) $user['id']);

        return [
            'success' => true,
            'status' => 200,
            'reason' => 'login_success',
            'message' => 'Connexion admin réussie.',
            'redirect_url' => $this->baseUrl('/admin/dashboard'),
            'user' => $this->sanitizeUser($user),
        ];
    }

    public function loginStudent(string $codeMassar, string $password, int $classId, Request $request): array
    {
        $codeMassar = trim($codeMassar);

        if ($codeMassar === '' || $password === '' || $classId <= 0) {
            return $this->fail('Paramètres invalides.', 422, 'invalid_parameters');
        }

        $student = Database::fetchOne(
            "SELECT
                u.id,
                u.role_id,
                r.code AS role_code,
                u.numero,
                u.code_massar,
                u.password_hash,
                u.can_login,
                u.is_active,
                u.nom,
                u.prenom,
                u.nom_ar,
                u.prenom_ar,
                cs.class_id
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             INNER JOIN class_students cs ON cs.user_id = u.id
             WHERE r.code = 'student'
               AND u.code_massar = :code_massar
               AND cs.class_id = :class_id
             LIMIT 1",
            [
                'code_massar' => $codeMassar,
                'class_id' => $classId,
            ]
        );

        if (!$student) {
            return $this->fail('Identifiants invalides.', 401, 'invalid_credentials');
        }

        if (!(bool) $student['is_active']) {
            return $this->fail('Compte désactivé.', 403, 'account_disabled');
        }

        if (!(bool) $student['can_login']) {
            return $this->fail('Connexion non autorisée pour cet élève.', 403, 'student_login_disabled');
        }

        if (!password_verify($password, (string) $student['password_hash'])) {
            return $this->fail('Identifiants invalides.', 401, 'invalid_credentials');
        }

        $network = $this->networkComputerService->resolveAllowedComputerOrFail($request);

        if (!(bool) ($network['success'] ?? false)) {
            return [
                'success' => false,
                'status' => (int) ($network['status'] ?? 403),
                'reason' => 'unauthorized_computer',
                'message' => (string) ($network['message'] ?? 'Poste non autorisé.'),
                'network' => [
                    'ip' => (string) ($network['ip'] ?? ''),
                    'network_type' => (string) ($network['network_type'] ?? 'unknown'),
                    'computer' => $network['computer'] ?? null,
                ],
            ];
        }

        $computer = is_array($network['computer'] ?? null) ? $network['computer'] : null;
        $computerId = $computer !== null ? (int) ($computer['id'] ?? 0) : 0;
        $ip = (string) ($network['ip'] ?? '');
        $networkType = (string) ($network['network_type'] ?? 'unknown');

        $activeSession = SessionManager::findActiveStudentSession((int) $student['id']);

        if ($activeSession !== null) {
            $existingComputerId = (int) ($activeSession['computer_id'] ?? 0);
            $existingIp = (string) ($activeSession['ip_address'] ?? '');
            $existingNetworkType = (string) ($activeSession['network_type'] ?? 'unknown');

            $sameComputer = $existingComputerId > 0 && $existingComputerId === $computerId;
            $sameIp = $existingIp !== '' && $ip !== '' && hash_equals($existingIp, $ip);
            $sameNetworkType = $existingNetworkType === $networkType;

            if (!($sameComputer && $sameIp && $sameNetworkType)) {
                $alertId = $this->alertService->createConcurrentLoginAlert(
                    $student,
                    $classId,
                    $activeSession,
                    $computer ?? [],
                    $ip,
                    $networkType,
                    'Tentative de double connexion refusée.'
                );

                return [
                    'success' => false,
                    'status' => 409,
                    'reason' => 'concurrent_session_detected',
                    'message' => 'Une session active existe déjà pour cet élève sur un autre poste.',
                    'alert_created' => $alertId > 0,
                    'alert_id' => $alertId > 0 ? $alertId : null,
                    'existing_session' => [
                        'id' => (int) ($activeSession['id'] ?? 0),
                        'computer_id' => $existingComputerId > 0 ? $existingComputerId : null,
                        'ip_address' => $existingIp,
                        'network_type' => $existingNetworkType,
                        'started_at' => (string) ($activeSession['started_at'] ?? ''),
                        'last_activity_at' => (string) ($activeSession['last_activity_at'] ?? ''),
                    ],
                    'attempted_computer' => $computer,
                ];
            }

            SessionManager::closeSessionById((int) $activeSession['id']);
        }

        SessionManager::login([
            'user_id' => (int) $student['id'],
            'role' => (string) $student['role_code'],
            'role_id' => (int) $student['role_id'],
            'class_id' => (int) $student['class_id'],
            'display_name' => trim(((string) $student['nom']) . ' ' . ((string) $student['prenom'])),
            'numero' => (int) $student['numero'],
        ], [
            'user_id' => (int) $student['id'],
            'class_id' => (int) $student['class_id'],
            'computer_id' => $computerId > 0 ? $computerId : null,
            'network_type' => $networkType,
            'ip_address' => $ip,
        ]);

        $this->updateLastLogin((int) $student['id']);

        return [
            'success' => true,
            'status' => 200,
            'reason' => 'login_success',
            'message' => 'Connexion élève réussie.',
            'redirect_url' => $this->baseUrl('/student/dashboard'),
            'user' => $this->sanitizeUser($student),
            'network' => [
                'ip' => $ip,
                'network_type' => $networkType,
                'computer' => $computer,
            ],
        ];
    }

    public function logout(): void
    {
        SessionManager::logout(true, true);
    }

    public function currentUser(): ?array
    {
        $userId = SessionManager::id();
        if ($userId === null) {
            return null;
        }

        $user = Database::fetchOne(
            "SELECT
                u.id,
                u.role_id,
                r.code AS role_code,
                u.numero,
                u.code_massar,
                u.can_login,
                u.is_active,
                u.nom,
                u.prenom,
                u.nom_ar,
                u.prenom_ar,
                u.last_login_at,
                cs.class_id
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             LEFT JOIN class_students cs ON cs.user_id = u.id
             WHERE u.id = :id
             LIMIT 1",
            ['id' => $userId]
        );

        return $user ? $this->sanitizeUser($user) : null;
    }

    public function refreshAuthenticatedSession(bool $touchDatabase = true): void
    {
        SessionManager::enforceTimeout();
        SessionManager::enforceIntegrity(false, true);

        if (!SessionManager::check()) {
            return;
        }

        $auth = SessionManager::auth();

        if (!is_array($auth)) {
            SessionManager::logout(false);
            return;
        }

        $currentDbSession = SessionManager::currentDatabaseSession();

        /*
        |------------------------------------------------------------------
        | Important :
        | Si la session PHP existe mais que la session DB active n'existe plus,
        | cela signifie qu'elle a été fermée à distance (monitoring, blocage,
        | logout administrateur, etc.).
        |
        | Dans ce cas on NE RECRÉE PAS la session.
        | On déconnecte proprement l'utilisateur.
        |------------------------------------------------------------------
        */
        if ($currentDbSession === null) {
            SessionManager::logout(false);
            return;
        }

        if ($touchDatabase) {
            static $lastRefreshAt = 0;
            $now = time();

            if (($now - $lastRefreshAt) >= $this->heartbeatIntervalSeconds()) {
                SessionManager::touch(true);
                $lastRefreshAt = $now;
                return;
            }
        }

        SessionManager::touch(false);
    }

    private function updateLastLogin(int $userId): void
    {
        if ($userId <= 0) {
            throw new RuntimeException('User ID invalide.');
        }

        Database::execute(
            "UPDATE users
             SET last_login_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id",
            ['id' => $userId]
        );
    }

    private function sanitizeUser(array $user): array
    {
        return [
            'id' => (int) ($user['id'] ?? 0),
            'role' => (string) ($user['role_code'] ?? ''),
            'numero' => isset($user['numero']) ? (int) $user['numero'] : null,
            'code_massar' => (string) ($user['code_massar'] ?? ''),
            'nom' => (string) ($user['nom'] ?? ''),
            'prenom' => (string) ($user['prenom'] ?? ''),
            'nom_ar' => (string) ($user['nom_ar'] ?? ''),
            'prenom_ar' => (string) ($user['prenom_ar'] ?? ''),
            'class_id' => isset($user['class_id']) ? (int) $user['class_id'] : null,
            'can_login' => isset($user['can_login']) ? (bool) $user['can_login'] : null,
            'is_active' => isset($user['is_active']) ? (bool) $user['is_active'] : null,
            'display_name' => trim(((string) ($user['nom'] ?? '')) . ' ' . ((string) ($user['prenom'] ?? ''))),
        ];
    }

    private function fail(string $message, int $status = 400, string $reason = 'error'): array
    {
        return [
            'success' => false,
            'status' => $status,
            'reason' => $reason,
            'message' => $message,
        ];
    }

    private function baseUrl(string $path = ''): string
    {
        $baseUrl = (string) Config::get('app.base_url', '');
        $baseUrl = rtrim($baseUrl, '/');

        if ($path === '') {
            return $baseUrl !== '' ? $baseUrl : '/';
        }

        return $baseUrl . '/' . ltrim($path, '/');
    }

    private function heartbeatIntervalSeconds(): int
    {
        $value = (int) Config::get('app.session.heartbeat_interval', 30);
        return max(10, $value);
    }

    public function getActiveClasses(): array
    {
        $rows = Database::fetchAll(
            "SELECT
                id,
                name,
                school_year,
                is_active
            FROM classes
            WHERE is_active = 1
            ORDER BY name ASC, school_year DESC"
        );

        $items = [];

        foreach ($rows as $row) {
            $items[] = [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'school_year' => (string) ($row['school_year'] ?? ''),
                'label' => trim(
                    (string) ($row['name'] ?? '') .
                    (
                        !empty($row['school_year'])
                            ? ' (' . (string) $row['school_year'] . ')'
                            : ''
                    )
                ),
            ];
        }

        return $items;
    }

}
