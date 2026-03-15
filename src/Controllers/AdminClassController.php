<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\SessionManager;
use App\Services\AuthService;
use App\Services\ClassAdminService;
use App\Services\LoginAuthorizationService;

final class AdminClassController extends Controller
{
    private ClassAdminService $classService;
    private LoginAuthorizationService $authzService;
    private AuthService $authService;

    public function __construct()
    {
        parent::__construct();
        $this->classService = new ClassAdminService();
        $this->authzService = new LoginAuthorizationService();
        $this->authService = new AuthService();
    }

    public function index(): void
    {
        $this->guardAdmin();

        $this->render('admin.classes.index', [
            'title' => 'Gestion des classes',
            'classes' => $this->classService->listClasses(),
            'csrf_class_toggle' => Csrf::token('admin.class.toggle'),
            'csrf_class_auth' => Csrf::token('admin.class.auth'),
        ]);
    }

    public function show(string $id): void
    {
        $this->guardAdmin();

        $class = $this->classService->findClassById((int) $id);
        if ($class === null) {
            $this->abort(404, 'Classe introuvable.');
            return;
        }

        $students = $this->classService->getClassStudents((int) $id);

        $this->render('admin.classes.show', [
            'title' => 'Détail classe',
            'class' => $class,
            'students' => $students,
            'csrf_class_toggle' => Csrf::token('admin.class.toggle'),
            'csrf_class_auth' => Csrf::token('admin.class.auth'),
        ]);
    }

    public function toggleActive(): void
    {
        $this->guardAdmin();
        Csrf::assertRequest($this->request(), 'admin.class.toggle');

        $classId = $this->request()->int('class_id');
        $value = $this->request()->boolean('value');

        $this->classService->toggleClassActive($classId, $value);

        $this->redirect(base_url('admin/classes'));
    }

    public function allowClassLogin(): void
    {
        $this->guardAdmin();
        Csrf::assertRequest($this->request(), 'admin.class.auth');

        $classId = $this->request()->int('class_id');
        $this->authzService->allowClassLogin($classId);

        $this->redirect(base_url('admin/classes/' . $classId));
    }

    public function denyClassLogin(): void
    {
        $this->guardAdmin();
        Csrf::assertRequest($this->request(), 'admin.class.auth');

        $classId = $this->request()->int('class_id');
        $this->authzService->denyClassLogin($classId);

        $this->redirect(base_url('admin/classes/' . $classId));
    }

    public function allowGroupLogin(): void
    {
        $this->guardAdmin();
        Csrf::assertRequest($this->request(), 'admin.class.auth');

        $classId = $this->request()->int('class_id');
        $group = $this->request()->int('group');

        $this->authzService->allowGroupLogin($classId, $group);

        $this->redirect(base_url('admin/classes/' . $classId));
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