<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\SessionManager;
use App\Services\AuthService;
use App\Services\ClassAdminService;
use App\Services\ExamAdminService;

final class AdminExamController extends Controller
{
    private ExamAdminService $examService;
    private ClassAdminService $classService;
    private AuthService $authService;

    public function __construct()
    {
        parent::__construct();
        $this->examService = new ExamAdminService();
        $this->classService = new ClassAdminService();
        $this->authService = new AuthService();
    }

    public function index(): void
    {
        $this->guardAdmin();

        $this->render('admin.exams.index', [
            'title' => 'Gestion des examens',
            'exams' => $this->examService->listExams(),
            'csrf_exam_toggle' => Csrf::token('admin.exam.toggle'),
        ]);
    }

    public function show(string $id): void
    {
        $this->guardAdmin();

        $examId = (int) $id;
        $exam = $this->examService->findExamById($examId);

        if ($exam === null) {
            $this->abort(404, 'Examen introuvable.');
            return;
        }

        $classId = $this->request()->int('class_id');
        $results = $this->examService->getExamResults($examId, $classId > 0 ? $classId : null);

        $this->render('admin.exams.show', [
            'title' => 'Détail examen',
            'exam' => $exam,
            'results' => $results,
            'classes' => $this->classService->listClasses(),
            'selected_class_id' => $classId,
            'csrf_exam_toggle' => Csrf::token('admin.exam.toggle'),
        ]);
    }

    public function toggleActive(): void
    {
        $this->guardAdmin();
        Csrf::assertRequest($this->request(), 'admin.exam.toggle');

        $examId = $this->request()->int('exam_id');
        $value = $this->request()->boolean('value');

        $this->examService->toggleExamActive($examId, $value);

        $this->redirect(base_url('admin/exams'));
    }

    public function togglePrint(): void
    {
        $this->guardAdmin();
        Csrf::assertRequest($this->request(), 'admin.exam.toggle');

        $examId = $this->request()->int('exam_id');
        $value = $this->request()->boolean('value');

        $this->examService->toggleExamPrint($examId, $value);

        $this->redirect(base_url('admin/exams'));
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