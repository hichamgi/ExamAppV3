<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\SessionManager;
use App\Services\AuthService;
use App\Services\ClassAdminService;
use App\Services\StudentAdminService;

final class AdminStudentController extends Controller
{
    private StudentAdminService $studentService;
    private ClassAdminService $classService;
    private AuthService $authService;

    public function __construct()
    {
        parent::__construct();
        $this->studentService = new StudentAdminService();
        $this->classService = new ClassAdminService();
        $this->authService = new AuthService();
    }

    public function index(): void
    {
        $this->guardAdmin();

        $search = $this->request()->string('search');
        $classId = $this->request()->filled('class_id')
            ? $this->request()->int('class_id')
            : 0;

        $canLogin = $this->request()->filled('can_login')
            ? $this->request()->int('can_login')
            : null;

        $isActive = $this->request()->filled('is_active')
            ? $this->request()->int('is_active')
            : null;

        $page = $this->request()->int('page', 1);

        $students = $this->studentService->paginateStudents(
            $search,
            $classId > 0 ? $classId : null,
            $canLogin,
            $isActive,
            $page,
            25
        );

        $this->render('admin.students.index', [
            'title' => 'Gestion des élèves',
            'students' => $students,
            'classes' => $this->classService->listClasses(),
            'filters' => [
                'search' => $search,
                'class_id' => $classId,
                'can_login' => $canLogin,
                'is_active' => $isActive,
            ],
            'csrf_student_toggle' => Csrf::token('admin.student.toggle'),
            'csrf_student_logout' => Csrf::token('admin.student.logout'),
        ]);
    }

    public function show(string $id): void
    {
        $this->guardAdmin();

        $student = $this->studentService->findStudentById((int) $id);
        if ($student === null) {
            $this->abort(404, 'Élève introuvable.');
            return;
        }

        $history = $this->studentService->getStudentExamHistory((int) $id);

        $this->render('admin.students.show', [
            'title' => 'Détail élève',
            'student' => $student,
            'history' => $history,
            'csrf_student_toggle' => Csrf::token('admin.student.toggle'),
            'csrf_student_logout' => Csrf::token('admin.student.logout'),
        ]);
    }

    public function toggleActive(): void
    {
        $this->guardAdmin();
        Csrf::assertRequest($this->request(), 'admin.student.toggle');

        $userId = $this->request()->int('user_id');
        $value = $this->request()->boolean('value');

        $this->studentService->toggleStudentActive($userId, $value);

        $this->redirect(base_url('admin/students'));
    }

    public function toggleCanLogin(): void
    {
        $this->guardAdmin();
        Csrf::assertRequest($this->request(), 'admin.student.toggle');

        $userId = $this->request()->int('user_id');
        $value = $this->request()->boolean('value');

        $this->studentService->toggleStudentCanLogin($userId, $value);

        $this->redirect(base_url('admin/students'));
    }

    public function forceLogout(): void
    {
        $this->guardAdmin();
        Csrf::assertRequest($this->request(), 'admin.student.logout');

        $userId = $this->request()->int('user_id');
        $this->studentService->forceLogoutStudent($userId);

        $this->redirect(base_url('admin/students'));
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