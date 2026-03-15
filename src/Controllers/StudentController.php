<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\SessionManager;
use App\Services\AuthService;
use App\Services\NetworkComputerService;

final class StudentController extends Controller
{
    private AuthService $authService;
    private NetworkComputerService $networkComputerService;

    public function __construct()
    {
        parent::__construct();
        $this->authService = new AuthService();
        $this->networkComputerService = new NetworkComputerService();
    }

    public function dashboard(): void
    {
        SessionManager::enforceTimeout();
        SessionManager::enforceIntegrity(false, true);

        if (!SessionManager::check() || !SessionManager::isStudent()) {
            $this->redirect($this->baseUrl('/login'));
            return;
        }

        $network = $this->networkComputerService->resolveClientComputer($this->request());

        if (!(bool) $network['allowed']) {
            SessionManager::logout(true, true);
            $this->redirect($this->baseUrl('/login'));
            return;
        }

        $dbSession = SessionManager::currentDatabaseSession();

        if ($dbSession === null) {
            SessionManager::logout(true, true);
            $this->redirect($this->baseUrl('/login'));
            return;
        }

        $expectedComputerId = (int) ($dbSession['computer_id'] ?? 0);
        $ip = $this->request()->ip();

        if ($expectedComputerId > 0 && !$this->networkComputerService->isExpectedComputerForSession($expectedComputerId, $ip)) {
            SessionManager::logout(true, true);
            $this->redirect($this->baseUrl('/login'));
            return;
        }

        $this->authService->refreshAuthenticatedSession(true);

        $student = $this->authService->currentUser();
        $activeExam = $this->getStudentActiveExam(SessionManager::id(), (int) ($student['class_id'] ?? 0));

        $this->render('student.dashboard', [
            'title' => 'Espace élève',
            'student' => $student,
            'network' => $network,
            'active_exam' => $activeExam,
            'csrf_logout' => Csrf::token('auth.logout'),
            'csrf_heartbeat' => Csrf::token('student.heartbeat'),
        ], 'layouts.main');
    }

    public function sessionInfo(): void
    {
        SessionManager::enforceTimeout();
        SessionManager::enforceIntegrity(false, true);

        if (!SessionManager::check() || !SessionManager::isStudent()) {
            $this->json([
                'success' => false,
                'message' => 'Non authentifié.',
            ], 401);
            return;
        }

        $this->authService->refreshAuthenticatedSession(true);

        $student = $this->authService->currentUser();
        $network = $this->networkComputerService->resolveClientComputer($this->request());
        $dbSession = SessionManager::currentDatabaseSession();

        $this->json([
            'success' => true,
            'student' => $student,
            'network' => $network,
            'session' => $dbSession ? $this->normalizeDbSession($dbSession) : null,
            'csrf' => [
                'heartbeat' => Csrf::token('student.heartbeat'),
                'logout' => Csrf::token('auth.logout'),
            ],
        ]);
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

        Csrf::assertRequest($this->request(), 'student.heartbeat');

        SessionManager::enforceTimeout();
        SessionManager::enforceIntegrity(false, true);

        if (!SessionManager::check() || !SessionManager::isStudent()) {
            $this->json([
                'success' => false,
                'message' => 'Session invalide.',
            ], 401);
            return;
        }

        $network = $this->networkComputerService->resolveAllowedComputerOrFail($this->request());

        if (!(bool) ($network['success'] ?? false)) {
            SessionManager::logout(true, true);

            $this->json([
                'success' => false,
                'message' => (string) ($network['message'] ?? 'Poste non autorisé.'),
                'network' => $network,
            ], 403);
            return;
        }

        $computerId = (int) ($network['computer_id'] ?? 0);
        $ip = (string) ($network['ip'] ?? '');

        $dbSession = SessionManager::currentDatabaseSession();

        if ($dbSession === null) {
            SessionManager::logout(true, true);

            $this->json([
                'success' => false,
                'message' => 'Session applicative introuvable.',
            ], 401);
            return;
        }

        if ($computerId > 0 && !SessionManager::isCurrentSessionMatchingComputer($computerId, $ip)) {
            SessionManager::logout(true, true);

            $this->json([
                'success' => false,
                'message' => 'Le poste actuel ne correspond pas à la session autorisée.',
            ], 403);
            return;
        }

        SessionManager::touch(true);

        $this->json([
            'success' => true,
            'message' => 'Heartbeat OK.',
            'server_time' => date('Y-m-d H:i:s'),
            'network' => [
                'ip' => $ip,
                'network_type' => (string) ($network['network_type'] ?? 'unknown'),
                'computer' => $network['computer'] ?? null,
            ],
        ]);
    }

    public function myExamStatus(): void
    {
        SessionManager::enforceTimeout();
        SessionManager::enforceIntegrity(false, true);

        if (!SessionManager::check() || !SessionManager::isStudent()) {
            $this->json([
                'success' => false,
                'message' => 'Non authentifié.',
            ], 401);
            return;
        }

        $student = $this->authService->currentUser();
        $userId = (int) ($student['id'] ?? 0);
        $classId = (int) ($student['class_id'] ?? 0);

        $activeExam = $this->getStudentActiveExam($userId, $classId);

        $this->json([
            'success' => true,
            'active_exam' => $activeExam,
        ]);
    }

    private function getStudentActiveExam(?int $userId, int $classId): ?array
    {
        if (($userId ?? 0) <= 0 || $classId <= 0) {
            return null;
        }

        $row = Database::fetchOne(
            "SELECT
                ue.id AS user_exam_id,
                ue.exam_id,
                ue.class_id,
                ue.status,
                ue.score,
                ue.started_at,
                ue.submitted_at,
                ue.duration_seconds,
                e.code,
                e.title,
                e.duration_minutes,
                e.is_active
             FROM user_exams ue
             INNER JOIN exams e ON e.id = ue.exam_id
             WHERE ue.user_id = :user_id
               AND ue.class_id = :class_id
               AND ue.status IN ('assigned', 'started')
             ORDER BY ue.id DESC
             LIMIT 1",
            [
                'user_id' => $userId,
                'class_id' => $classId,
            ]
        );

        if (!$row) {
            return null;
        }

        return [
            'user_exam_id' => (int) $row['user_exam_id'],
            'exam_id' => (int) $row['exam_id'],
            'class_id' => (int) $row['class_id'],
            'status' => (string) $row['status'],
            'score' => (float) $row['score'],
            'started_at' => (string) ($row['started_at'] ?? ''),
            'submitted_at' => (string) ($row['submitted_at'] ?? ''),
            'duration_seconds' => (int) $row['duration_seconds'],
            'code' => (string) $row['code'],
            'title' => (string) $row['title'],
            'duration_minutes' => (int) $row['duration_minutes'],
            'is_active' => (bool) $row['is_active'],
        ];
    }

    private function normalizeDbSession(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'user_id' => (int) ($row['user_id'] ?? 0),
            'class_id' => isset($row['class_id']) ? (int) $row['class_id'] : null,
            'computer_id' => isset($row['computer_id']) ? (int) $row['computer_id'] : null,
            'ip_address' => (string) ($row['ip_address'] ?? ''),
            'network_type' => (string) ($row['network_type'] ?? 'unknown'),
            'status' => (string) ($row['status'] ?? ''),
            'started_at' => (string) ($row['started_at'] ?? ''),
            'last_activity_at' => (string) ($row['last_activity_at'] ?? ''),
            'closed_at' => (string) ($row['closed_at'] ?? ''),
        ];
    }
}
