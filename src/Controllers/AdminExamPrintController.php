<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\SessionManager;
use App\Services\AuthService;
use App\Services\ExamPrintService;

class AdminExamPrintController extends Controller
{
    private ExamPrintService $service;
    private AuthService $authService;

    public function __construct()
    {
        parent::__construct();

        $this->service = new ExamPrintService();
        $this->authService = new AuthService();
    }

    public function printTickets(string $id): void
    {
        $this->guardAdmin();

        $examId = (int) $id;

        $this->render('admin.exams.print.tickets', [
            'title' => 'Impression tickets',
            'exam_id' => $examId,
            'tickets' => $this->service->getTicketsByExam($examId),
        ], null);
    }

    public function printStudent(string $id, string $user_exam_id): void
    {
        $this->guardAdmin();

        $examId = (int) $id;
        $userExamId = (int) $user_exam_id;
        $data = $this->service->getStudentCopy($userExamId);

        $this->render('admin.exams.print.student_copy', [
            'title' => 'Impression copie élève',
            'exam_id' => $examId,
            'student' => $data['student'],
            'questions' => $data['questions'],
        ], null);
    }

    public function printAll(string $id): void
    {
        $this->guardAdmin();

        $examId = (int) $id;
        $classId = (int) ($_GET['class_id'] ?? 0);

        if ($classId <= 0) {
            $this->abort(400, 'class_id obligatoire');
            return;
        }

        $this->render('admin.exams.print.all_copies', [
            'title' => 'Impression copies',
            'exam_id' => $examId,
            'class_id' => $classId,
            'copies' => $this->service->getAllCopies($examId, $classId),
        ], null);
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