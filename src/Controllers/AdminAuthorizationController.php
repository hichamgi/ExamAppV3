<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\SessionManager;
use App\Services\AuthService;
use App\Services\ClassAdminService;
use App\Services\LoginAuthorizationService;
use App\Services\StudentAdminService;

final class AdminAuthorizationController extends Controller
{
    private LoginAuthorizationService $authorizationService;
    private ClassAdminService $classService;
    private StudentAdminService $studentService;
    private AuthService $authService;

    public function __construct()
    {
        parent::__construct();
        $this->authorizationService = new LoginAuthorizationService();
        $this->classService = new ClassAdminService();
        $this->studentService = new StudentAdminService();
        $this->authService = new AuthService();
    }

    public function index(): void
    {
        $this->guardAdmin();

        $this->render('admin.authorizations.index', [
            'title' => 'Autorisations de connexion',
            'classes' => $this->classService->listClasses(),
            'students' => $this->studentService->paginateStudents('', null, null, null, 1, 20),
            'csrf_authorization' => Csrf::token('admin.authorization'),
        ]);
    }

    public function allowStudent(): void
    {
        $this->guardAdmin();
        Csrf::assertRequest($this->request(), 'admin.authorization');

        $userId = $this->request()->int('user_id');
        $this->authorizationService->allowStudentLogin($userId);

        $this->redirect(base_url('admin/authorizations'));
    }

    public function denyStudent(): void
    {
        $this->guardAdmin();
        Csrf::assertRequest($this->request(), 'admin.authorization');

        $userId = $this->request()->int('user_id');
        $this->authorizationService->denyStudentLogin($userId);

        $this->redirect(base_url('admin/authorizations'));
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