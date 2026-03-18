<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\SessionManager;
use App\Services\AuthService;
use App\Services\ClassAdminService;
use App\Services\ExamAdminService;

class AdminExamController extends Controller
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

        $classId = isset($_GET['class_id']) ? (int) $_GET['class_id'] : 0;
        $results = $this->examService->getExamResults($examId, $classId > 0 ? $classId : null);
        $assignmentData = $this->examService->getExamAssignmentData($examId);

        $this->render('admin.exams.show', [
            'title' => 'Détail examen',
            'exam' => $exam,
            'results' => $results,
            'classes' => $this->classService->listClasses(),
            'selected_class_id' => $classId,
            'assignment_data' => $assignmentData,
            'csrf_exam_toggle' => Csrf::token('admin.exam.toggle'),
            'csrf_exam_assignment' => Csrf::token('admin.exam.assignment'),
        ]);
    }

    public function toggleActive(): void
    {
        $this->guardAdmin();
        Csrf::assertRequest(request(), 'admin.exam.toggle');

        $examId = isset($_POST['exam_id']) ? (int) $_POST['exam_id'] : 0;
        $value = isset($_POST['value']) ? (int) $_POST['value'] : 0;

        $this->examService->toggleExamActive($examId, $value === 1);
        $this->redirect(base_url('admin/exams'));
    }

    public function togglePrint(): void
    {
        $this->guardAdmin();
        Csrf::assertRequest(request(), 'admin.exam.toggle');

        $examId = isset($_POST['exam_id']) ? (int) $_POST['exam_id'] : 0;
        $value = isset($_POST['value']) ? (int) $_POST['value'] : 0;

        $this->examService->toggleExamPrint($examId, $value === 1);
        $this->redirect(base_url('admin/exams'));
    }

    public function saveAssignment(): void
    {
        $this->guardAdmin();
        Csrf::assertRequest(request(), 'admin.exam.assignment');

        $examId = isset($_POST['exam_id']) ? (int) $_POST['exam_id'] : 0;

        $this->examService->saveExamAssignment($examId, $_POST);

        $this->redirect(base_url('admin/exams/' . $examId));
    }

    public function exportSemester(): void
    {
        $this->guardAdmin();

        $semester = isset($_GET['semester']) ? strtolower(trim((string) $_GET['semester'])) : '';

        if (!in_array($semester, ['s1', 's2'], true)) {
            $this->abort(400, 'Semestre invalide.');
            return;
        }

        $csv = $this->examService->buildSemesterCsv($semester);
        $filename = 'notes_' . $semester . '_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        echo $csv;
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

        $this->authService->refreshAuthenticatedSession(false);
    }
}