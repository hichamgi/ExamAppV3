<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\SessionManager;
use App\Services\AlertService;
use App\Services\AuthService;
use App\Services\NetworkComputerService;

final class AdminController extends Controller
{
    private AuthService $authService;
    private NetworkComputerService $networkComputerService;
    private AlertService $alertService;
    private const ADMIN_STALE_MINUTES = 240;

    public function __construct()
    {
        parent::__construct();
        $this->authService = new AuthService();
        $this->networkComputerService = new NetworkComputerService();
        $this->alertService = new AlertService();
    }

    public function dashboard(): void
    {
        SessionManager::enforceTimeout();
        SessionManager::enforceIntegrity(false, true);

        if (!SessionManager::check() || !SessionManager::isAdmin()) {
            $this->redirect($this->baseUrl('/login'));
            return;
        }

        $this->authService->refreshAuthenticatedSession(false);

        $stats = [
            'active_sessions' => $this->countActiveSessions(),
            'active_students' => $this->countActiveStudentSessions(),
            'open_alerts' => $this->countOpenAlerts(),
            'active_computers' => $this->countActiveComputers(),
        ];

        $this->render('admin.dashboard', [
            'title' => 'Dashboard admin',
            'admin' => $this->authService->currentUser(),
            'stats' => $stats,
            'csrf_logout' => Csrf::token('auth.logout'),
            'csrf_force_logout' => Csrf::token('admin.force-logout'),
            'csrf_alert_update' => Csrf::token('admin.alert-update'),
            'csrf_disable_student' => Csrf::token('admin.disable-student'),
            'csrf_heartbeat' => \App\Core\Csrf::token('admin.heartbeat'),
        ], 'layouts.main');
    }

    public function activeSessions(): void
    {
        $this->guardAdminApi();

        $rows = Database::fetchAll(
            "SELECT
                us.id,
                us.user_id,
                us.class_id,
                us.computer_id,
                us.ip_address,
                us.network_type,
                us.status,
                us.started_at,
                us.last_activity_at,
                CASE
                    WHEN r.code = 'admin'
                        AND us.last_activity_at < (NOW() - INTERVAL " . self::ADMIN_STALE_MINUTES . " MINUTE)
                    THEN 1
                    ELSE 0
                END AS is_stale,
                u.numero,
                u.code_massar,
                u.nom,
                u.prenom,
                c.name AS class_name,
                r.code AS role_code,
                lc.name AS computer_name,
                lc.hostname,
                lc.room_name
            FROM user_sessions us
            INNER JOIN users u ON u.id = us.user_id
            INNER JOIN roles r ON r.id = u.role_id
            LEFT JOIN classes c ON c.id = us.class_id
            LEFT JOIN lab_computers lc ON lc.id = us.computer_id
            WHERE us.status = 'active'
            ORDER BY us.last_activity_at DESC"
        );

        $items = array_map(fn(array $row): array => $this->normalizeActiveSession($row), $rows);

        $this->json([
            'success' => true,
            'items' => $items,
            'total' => count($items),
        ]);
    }

    public function loginAlerts(): void
    {
        $this->guardAdminApi();

        $rows = Database::fetchAll(
            "SELECT
                la.id,
                la.user_id,
                la.username_attempted,
                la.class_id,
                la.existing_session_id,
                la.existing_computer_id,
                la.existing_ip,
                la.attempted_computer_id,
                la.attempted_ip,
                la.attempted_network_type,
                la.attempted_at,
                la.status,
                la.notes,
                la.created_at,
                u.numero,
                u.nom,
                u.prenom,
                c.name AS class_name,
                existing_pc.name AS existing_computer_name,
                attempted_pc.name AS attempted_computer_name
             FROM login_attempt_alerts la
             LEFT JOIN users u ON u.id = la.user_id
             LEFT JOIN classes c ON c.id = la.class_id
             LEFT JOIN lab_computers existing_pc ON existing_pc.id = la.existing_computer_id
             LEFT JOIN lab_computers attempted_pc ON attempted_pc.id = la.attempted_computer_id
             ORDER BY la.attempted_at DESC
             LIMIT 200"
        );

        $items = array_map(fn(array $row): array => $this->normalizeAlert($row), $rows);

        $this->json([
            'success' => true,
            'items' => $items,
            'total' => count($items),
        ]);
    }

    public function computers(): void
    {
        $this->guardAdminApi();

        $roomName = $this->request()->string('room_name');
        $items = $this->networkComputerService->listActiveComputers($roomName !== '' ? $roomName : null);

        $this->json([
            'success' => true,
            'items' => $items,
            'total' => count($items),
        ]);
    }

    public function forceLogoutSession(): void
    {
        $this->guardAdminApi(true);
        Csrf::assertRequest($this->request(), 'admin.force-logout');

        $sessionId = $this->request()->int('session_id');

        if ($sessionId <= 0) {
            $this->json([
                'success' => false,
                'message' => 'session_id invalide.',
            ], 422);
            return;
        }

        $affected = SessionManager::closeSessionById($sessionId);

        $this->json([
            'success' => $affected > 0,
            'message' => $affected > 0 ? 'Session fermée.' : 'Aucune session active correspondante.',
        ], $affected > 0 ? 200 : 404);
    }

    public function updateAlertStatus(): void
    {
        $this->guardAdminApi(true);
        Csrf::assertRequest($this->request(), 'admin.alert-update');

        $alertId = $this->request()->int('alert_id');
        $status = $this->request()->string('status');
        $notes = $this->request()->string('notes');

        $affected = $this->alertService->updateStatus($alertId, $status, $notes);

        $this->json([
            'success' => $affected > 0,
            'message' => $affected > 0 ? 'Alerte mise à jour.' : 'Alerte introuvable ou paramètres invalides.',
        ], $affected > 0 ? 200 : 422);
    }

    public function disableStudent(): void
    {
        $this->guardAdminApi(true);
        Csrf::assertRequest($this->request(), 'admin.disable-student');

        $userId = $this->request()->int('user_id');

        if ($userId <= 0) {
            $this->json([
                'success' => false,
                'message' => 'user_id invalide.',
            ], 422);
            return;
        }

        Database::execute(
            "UPDATE users
             SET is_active = 0,
                 can_login = 0,
                 updated_at = NOW()
             WHERE id = :id",
            ['id' => $userId]
        );

        Database::execute(
            "UPDATE user_sessions
             SET status = 'closed',
                 closed_at = NOW(),
                 updated_at = NOW()
             WHERE user_id = :id
               AND status = 'active'",
            ['id' => $userId]
        );

        $this->json([
            'success' => true,
            'message' => 'Compte élève désactivé et sessions fermées.',
        ]);
    }

    public function roomOverview(): void
    {
        $this->guardAdminApi();

        $computers = $this->networkComputerService->listActiveComputers();
        $sessions = Database::fetchAll(
            "SELECT
                us.id,
                us.user_id,
                us.class_id,
                us.computer_id,
                us.ip_address,
                us.network_type,
                us.started_at,
                us.last_activity_at,
                u.numero,
                u.nom,
                u.prenom,
                c.name AS class_name
             FROM user_sessions us
             INNER JOIN users u ON u.id = us.user_id
             LEFT JOIN classes c ON c.id = us.class_id
             WHERE us.status = 'active'"
        );

        $sessionByComputer = [];
        foreach ($sessions as $session) {
            $computerId = (int) ($session['computer_id'] ?? 0);
            if ($computerId > 0) {
                $sessionByComputer[$computerId] = $this->normalizeRoomSession($session);
            }
        }

        $items = [];
        foreach ($computers as $computer) {
            $items[] = [
                'computer' => $computer,
                'occupied' => isset($sessionByComputer[$computer['id']]),
                'session' => $sessionByComputer[$computer['id']] ?? null,
            ];
        }

        $this->json([
            'success' => true,
            'items' => $items,
            'total' => count($items),
        ]);
    }

    private function guardAdminApi(bool $touchSession = false): void
    {
        SessionManager::enforceTimeout();
        SessionManager::enforceIntegrity(false, true);

        if (!SessionManager::check() || !SessionManager::isAdmin()) {
            $this->json([
                'success' => false,
                'message' => 'Accès interdit.',
            ], 403);
            exit;
        }

        if ($touchSession) {
            $this->authService->refreshAuthenticatedSession(false);
        }
    }

    private function countActiveSessions(): int
    {
        return (int) Database::fetchValue(
            "SELECT COUNT(*) FROM user_sessions WHERE status = 'active'",
            [],
            0
        );
    }

    private function countActiveStudentSessions(): int
    {
        return (int) Database::fetchValue(
            "SELECT COUNT(*)
             FROM user_sessions us
             INNER JOIN users u ON u.id = us.user_id
             INNER JOIN roles r ON r.id = u.role_id
             WHERE us.status = 'active'
               AND r.code = 'student'",
            [],
            0
        );
    }

    private function countOpenAlerts(): int
    {
        return (int) Database::fetchValue(
            "SELECT COUNT(*)
             FROM login_attempt_alerts
             WHERE status IN ('refused', 'suspect')",
            [],
            0
        );
    }

    private function countActiveComputers(): int
    {
        return (int) Database::fetchValue(
            "SELECT COUNT(*) FROM lab_computers WHERE is_active = 1",
            [],
            0
        );
    }

    private function normalizeActiveSession(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'user_id' => (int) ($row['user_id'] ?? 0),
            'class_id' => isset($row['class_id']) ? (int) $row['class_id'] : null,
            'computer_id' => isset($row['computer_id']) ? (int) $row['computer_id'] : null,
            'role' => (string) ($row['role_code'] ?? ''),
            'numero' => isset($row['numero']) ? (int) $row['numero'] : null,
            'code_massar' => (string) ($row['code_massar'] ?? ''),
            'student_name' => trim(((string) ($row['nom'] ?? '')) . ' ' . ((string) ($row['prenom'] ?? ''))),
            'class_name' => (string) ($row['class_name'] ?? ''),
            'computer_name' => (string) ($row['computer_name'] ?? ''),
            'hostname' => (string) ($row['hostname'] ?? ''),
            'room_name' => (string) ($row['room_name'] ?? ''),
            'ip_address' => (string) ($row['ip_address'] ?? ''),
            'network_type' => (string) ($row['network_type'] ?? 'unknown'),
            'status' => (string) ($row['status'] ?? ''),
            'started_at' => (string) ($row['started_at'] ?? ''),
            'last_activity_at' => (string) ($row['last_activity_at'] ?? ''),
            'is_stale' => !empty($row['is_stale']),
        ];
    }

    private function normalizeAlert(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'user_id' => isset($row['user_id']) ? (int) $row['user_id'] : null,
            'numero' => isset($row['numero']) ? (int) $row['numero'] : null,
            'student_name' => trim(((string) ($row['nom'] ?? '')) . ' ' . ((string) ($row['prenom'] ?? ''))),
            'username_attempted' => (string) ($row['username_attempted'] ?? ''),
            'class_id' => isset($row['class_id']) ? (int) $row['class_id'] : null,
            'class_name' => (string) ($row['class_name'] ?? ''),
            'existing_session_id' => isset($row['existing_session_id']) ? (int) $row['existing_session_id'] : null,
            'existing_computer_id' => isset($row['existing_computer_id']) ? (int) $row['existing_computer_id'] : null,
            'existing_computer_name' => (string) ($row['existing_computer_name'] ?? ''),
            'existing_ip' => (string) ($row['existing_ip'] ?? ''),
            'attempted_computer_id' => isset($row['attempted_computer_id']) ? (int) $row['attempted_computer_id'] : null,
            'attempted_computer_name' => (string) ($row['attempted_computer_name'] ?? ''),
            'attempted_ip' => (string) ($row['attempted_ip'] ?? ''),
            'attempted_network_type' => (string) ($row['attempted_network_type'] ?? 'unknown'),
            'attempted_at' => (string) ($row['attempted_at'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'notes' => (string) ($row['notes'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }

    private function normalizeRoomSession(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'user_id' => (int) ($row['user_id'] ?? 0),
            'class_id' => isset($row['class_id']) ? (int) $row['class_id'] : null,
            'computer_id' => isset($row['computer_id']) ? (int) $row['computer_id'] : null,
            'numero' => isset($row['numero']) ? (int) $row['numero'] : null,
            'student_name' => trim(((string) ($row['nom'] ?? '')) . ' ' . ((string) ($row['prenom'] ?? ''))),
            'class_name' => (string) ($row['class_name'] ?? ''),
            'ip_address' => (string) ($row['ip_address'] ?? ''),
            'network_type' => (string) ($row['network_type'] ?? 'unknown'),
            'started_at' => (string) ($row['started_at'] ?? ''),
            'last_activity_at' => (string) ($row['last_activity_at'] ?? ''),
        ];
    }

    public function heartbeat(): void
    {
        if (!$this->request()->isPost()) {
            $this->json([
                'success' => false,
                'message' => 'Méthode non autorisée.',
            ], 405);
            return;
        }

        Csrf::assertRequest($this->request(), 'admin.heartbeat');

        SessionManager::enforceTimeout();
        SessionManager::enforceIntegrity(false, true);

        if (!SessionManager::check() || !SessionManager::isAdmin()) {
            $this->json([
                'success' => false,
                'message' => 'Session invalide.',
            ], 401);
            return;
        }

        $dbSession = SessionManager::currentDatabaseSession();

        if ($dbSession === null) {
            SessionManager::logout(true, true);

            $this->json([
                'success' => false,
                'message' => 'Session applicative introuvable.',
            ], 401);
            return;
        }

        SessionManager::touch(true);

        $this->json([
            'success' => true,
            'message' => 'Heartbeat admin OK.',
            'server_time' => date('Y-m-d H:i:s'),
            'session' => [
                'session_id' => (int) ($dbSession['id'] ?? 0),
                'ip' => (string) ($dbSession['ip_address'] ?? ''),
                'network_type' => (string) ($dbSession['network_type'] ?? 'unknown'),
            ],
        ]);
    }
}
