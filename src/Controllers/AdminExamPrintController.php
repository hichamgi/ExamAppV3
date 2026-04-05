<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\ExamPrintService;

class AdminExamPrintController extends Controller
{
    private ExamPrintService $service;

    public function __construct()
    {
        parent::__construct();

        // Adapte ici selon ta classe Database :
        // $pdo = Database::connection();
        // $pdo = Database::getConnection();
        $pdo = Database::pdo();

        $this->service = new ExamPrintService($pdo);
    }

    public function printTickets(int $id): void
    {
        $tickets = $this->service->getTicketsByExam($id);

        $this->view('admin/exams/print/tickets', [
            'exam_id' => $id,
            'tickets' => $tickets,
        ]);
    }

    public function printStudent(int $id, int $user_exam_id): void
    {
        $data = $this->service->getStudentCopy($user_exam_id);

        $this->view('admin/exams/print/student_copy', $data);
    }

    public function printAll(int $id): void
    {
        $classId = (int) ($_GET['class_id'] ?? 0);

        if ($classId <= 0) {
            http_response_code(400);
            echo 'class_id obligatoire';
            return;
        }

        $copies = $this->service->getAllCopies($id, $classId);

        $this->view('admin/exams/print/all_copies', [
            'exam_id' => $id,
            'class_id' => $classId,
            'copies' => $copies,
        ]);
    }
}