<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\SessionManager;
use App\Services\AuthService;
use App\Services\MonitoringService;
use App\Core\Csrf;

final class AdminMonitoringController extends Controller
{
    private MonitoringService $monitoringService;
    private AuthService $authService;

    public function __construct()
    {
        parent::__construct();
        $this->monitoringService = new MonitoringService();
        $this->authService = new AuthService();
    }

    public function index(): void
    {
        $this->guardAdmin();

        $this->render('admin.monitoring.index', [
            'title' => 'Supervision',
            'stats' => $this->monitoringService->getDashboardStats(),
            'sessions' => $this->monitoringService->getActiveSessions(),
            'alerts' => $this->monitoringService->getRecentAlerts(30),
            'rooms' => $this->monitoringService->getRoomOverview(),
            'csrf_monitoring_action' => Csrf::token('admin.monitoring.action'),
        ]);
    }

    public function forceLogout(): void
    {
        $this->guardAdmin();
        Csrf::assertRequest($this->request(), 'admin.monitoring.action');

        $sessionId = $this->request()->int('session_id');

        $this->monitoringService->forceLogoutBySession($sessionId);

        $this->redirect(base_url('admin/monitoring'));
        exit;
    }

    public function blockStudent(): void
    {
        $this->guardAdmin();
        Csrf::assertRequest($this->request(), 'admin.monitoring.action');

        $sessionId = $this->request()->int('session_id');

        $this->monitoringService->blockStudentBySession($sessionId);

        $this->redirect(base_url('admin/monitoring'));
        exit;
    }

    public function markCheat(): void
    {
        $this->guardAdmin();
        Csrf::assertRequest($this->request(), 'admin.monitoring.action');

        $sessionId = $this->request()->int('session_id');

        $this->monitoringService->markCurrentExamAsCheatBySession($sessionId);

        $this->redirect(base_url('admin/monitoring'));
        exit;
    }

    public function forceLogoutIp(): void
    {
        $this->guardAdmin();
        Csrf::assertRequest($this->request(), 'admin.monitoring.action');

        $ipAddress = trim($this->request()->string('ip_address'));

        if ($ipAddress !== '') {
            $this->monitoringService->forceLogoutByIp($ipAddress);
        }

        $this->redirect(base_url('admin/monitoring'));
        exit;
    }

    private function guardAdmin(): void
    {
        SessionManager::enforceTimeout();
        SessionManager::enforceIntegrity(false, true);

        if (!SessionManager::check() || !SessionManager::isAdmin()) {
            $this->redirect(base_url('login'));
            exit;
        }

        $this->authService->refreshAuthenticatedSession(true);
    }
}