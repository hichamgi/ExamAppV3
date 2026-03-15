<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\SessionManager;
use App\Services\AuthService;
use App\Services\MonitoringService;

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
        ]);
    }

    private function guardAdmin(): void
    {
        SessionManager::enforceTimeout();
        SessionManager::enforceIntegrity(false, true);

        if (!SessionManager::check() || !SessionManager::isAdmin()) {
            $this->redirect(base_url('login'));
            exit;
        }

        $this->authService->refreshAuthenticatedSession(false);
    }
}