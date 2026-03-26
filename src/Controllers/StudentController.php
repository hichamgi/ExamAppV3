<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Config;
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

        if (!(bool) ($network['allowed'] ?? false)) {
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
        $userId = (int) ($student['id'] ?? 0);
        $classId = (int) ($student['class_id'] ?? 0);

        $activeExams = $this->getStudentActiveExams($userId, $classId);
        $completedExams = $this->getStudentCompletedExams($userId, $classId);

        $studentExamDebug = $_SESSION['student_exam_debug'] ?? null;
        unset($_SESSION['student_exam_debug']);

        $this->render('student.dashboard', [
            'title' => 'Espace élève',
            'student' => $student,
            'network' => $network,
            'active_exams' => $activeExams,
            'completed_exams' => $completedExams,
            'student_exam_debug' => $studentExamDebug,
            'csrf_logout' => Csrf::token('auth.logout'),
            'csrf_heartbeat' => Csrf::token('student.heartbeat'),
        ], 'layouts.main');
    }

    public function exam(): void
    {
        SessionManager::enforceTimeout();
        SessionManager::enforceIntegrity(false, true);

        if (!SessionManager::check() || !SessionManager::isStudent()) {
            $this->redirect($this->baseUrl('/login'));
            return;
        }

        $network = $this->networkComputerService->resolveClientComputer($this->request());

        if (!(bool) ($network['allowed'] ?? false)) {
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
        $userId = (int) ($student['id'] ?? 0);
        $classId = (int) ($student['class_id'] ?? 0);

        $requestedUserExamId = isset($_GET['user_exam_id']) ? (int) $_GET['user_exam_id'] : 0;

        if ($requestedUserExamId <= 0) {
            $this->redirect($this->baseUrl('/student/dashboard'));
            return;
        }

        $activeExam = $this->getStudentExamByUserExamId($requestedUserExamId, $userId, $classId);

        if ($activeExam === null) {
            $this->abort(404, 'Examen introuvable ou non autorisé.');
            return;
        }

        $questions = $this->getExamQuestions((int) $activeExam['user_exam_id']);

        $this->render('student.exam', [
            'title' => 'Passage examen',
            'student' => $student,
            'network' => $network,
            'active_exam' => $activeExam,
            'questions' => $questions,
            'csrf_logout' => Csrf::token('auth.logout'),
            'csrf_heartbeat' => Csrf::token('student.heartbeat'),
            'csrf_exam_submit' => Csrf::token('student.exam.submit'),
        ], 'layouts.main');
    }

    public function submitExam(): void
    {
        if (!$this->request()->isPost()) {
            $this->redirect($this->baseUrl('/student/dashboard'));
            return;
        }

        Csrf::assertRequest($this->request(), 'student.exam.submit');

        SessionManager::enforceTimeout();
        SessionManager::enforceIntegrity(false, true);

        if (!SessionManager::check() || !SessionManager::isStudent()) {
            $this->redirect($this->baseUrl('/login'));
            return;
        }

        $network = $this->networkComputerService->resolveAllowedComputerOrFail($this->request());

        if (!(bool) ($network['success'] ?? false)) {
            SessionManager::logout(true, true);
            $this->redirect($this->baseUrl('/login'));
            return;
        }

        $this->authService->refreshAuthenticatedSession(true);

        $student = $this->authService->currentUser();
        $userId = (int) ($student['id'] ?? 0);
        $classId = (int) ($student['class_id'] ?? 0);

        $userExamId = isset($_POST['user_exam_id']) ? (int) $_POST['user_exam_id'] : 0;

        if ($userExamId <= 0) {
            $this->redirect($this->baseUrl('/student/dashboard'));
            return;
        }

        $exam = $this->getStudentExamByUserExamId($userExamId, $userId, $classId);

        if ($exam === null) {
            $this->abort(404, 'Examen introuvable ou non autorisé.');
            return;
        }

        $questions = $this->getExamQuestions($userExamId);

        $answers = isset($_POST['answers']) && is_array($_POST['answers']) ? $_POST['answers'] : [];
        $answersMulti = isset($_POST['answers_multi']) && is_array($_POST['answers_multi']) ? $_POST['answers_multi'] : [];

        $summary = [
            'total_questions' => count($questions),
            'answered_questions' => 0,
            'correct_questions' => 0,
            'wrong_questions' => 0,
            'blank_questions' => 0,
            'final_score' => 0.0,
        ];

        $debugRows = [];
        $examSummary = $summary;

        Database::transaction(function () use (
            $questions,
            $answers,
            $answersMulti,
            $userExamId,
            &$examSummary,
            &$debugRows
        ): void {
            foreach ($questions as $question) {
                $userAnswerRowId = (int) ($question['id'] ?? 0);
                $snapshot = is_array($question['snapshot'] ?? null) ? $question['snapshot'] : [];
                $type = (string) ($snapshot['type'] ?? $snapshot['t'] ?? '');
                $points = (float) ($question['points'] ?? 0);

                $evaluation = $this->evaluateStudentAnswer(
                    $userAnswerRowId,
                    $type,
                    $snapshot,
                    $points,
                    $answers,
                    $answersMulti
                );

                if ($evaluation['is_answered']) {
                    $examSummary['answered_questions']++;
                } else {
                    $examSummary['blank_questions']++;
                }

                if ($evaluation['is_correct']) {
                    $examSummary['correct_questions']++;
                } elseif ($evaluation['is_answered']) {
                    $examSummary['wrong_questions']++;
                }

                $examSummary['final_score'] += (float) $evaluation['score'];

                $debugRows[] = [
                    'question_num' => (int) ($question['question_num'] ?? 0),
                    'type' => $type,
                    'question_text' => (string) ($snapshot['q'] ?? ''),
                    'student_answer' => (string) $evaluation['stored_answer_text'],
                    'expected_answer' => (string) $evaluation['expected_debug'],
                    'debug_fields' => $evaluation['debug_fields'] ?? [],
                    'score' => (float) $evaluation['score'],
                    'is_answered' => (bool) $evaluation['is_answered'],
                    'is_correct' => (bool) $evaluation['is_correct'],
                ];

                Database::update(
                    'user_answers',
                    [
                        'answer_text' => $evaluation['stored_answer_text'],
                        'awarded_points' => $evaluation['score'],
                    ],
                    'id = :update_answer_row_id AND user_exam_id = :update_answer_user_exam_id',
                    [
                        'update_answer_row_id' => $userAnswerRowId,
                        'update_answer_user_exam_id' => $userExamId,
                    ]
                );
            }

            Database::delete(
                'exam_results',
                'user_exam_id = :delete_exam_result_user_exam_id',
                [
                    'delete_exam_result_user_exam_id' => $userExamId,
                ]
            );

            Database::insert('exam_results', [
                'user_exam_id' => $userExamId,
                'total_questions' => $examSummary['total_questions'],
                'answered_questions' => $examSummary['answered_questions'],
                'correct_questions' => $examSummary['correct_questions'],
                'wrong_questions' => $examSummary['wrong_questions'],
                'blank_questions' => $examSummary['blank_questions'],
                'final_score' => round((float) $examSummary['final_score'], 2),
            ]);

            Database::update(
                'user_exams',
                [
                    'status' => 'submitted',
                    'submitted_at' => date('Y-m-d H:i:s'),
                    'started_at' => date('Y-m-d H:i:s'),
                    'is_absent' => 0,
                    'score' => round((float) $examSummary['final_score'], 2),
                ],
                'id = :submit_user_exam_id',
                [
                    'submit_user_exam_id' => $userExamId,
                ]
            );
        });

        $showDebug = (bool) Config::get('app.exam.debug_student_correction', false)
            && (bool) Config::get('app.debug', false);

        if ($showDebug) {
            $_SESSION['student_exam_debug'] = [
                'user_exam_id' => $userExamId,
                'summary' => $examSummary,
                'questions' => $debugRows,
            ];
        }

        $this->redirect($this->baseUrl('/student/dashboard'));
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

        $activeExams = $this->getStudentActiveExams($userId, $classId);

        $this->json([
            'success' => true,
            'active_exams' => $activeExams,
        ]);
    }

    private function getStudentActiveExams(?int $userId, int $classId): array
    {
        if (($userId ?? 0) <= 0 || $classId <= 0) {
            return [];
        }

        $rows = Database::fetchAll(
            "
            SELECT
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
            WHERE ue.user_id = :user_id_dashboard
              AND ue.class_id = :class_id_dashboard
              AND ue.status IN ('assigned', 'started')
              AND e.is_active = :exam_is_active_dashboard
            ORDER BY
                CASE
                    WHEN ue.status = 'started' THEN 0
                    WHEN ue.status = 'assigned' THEN 1
                    ELSE 2
                END,
                e.id ASC,
                ue.id ASC
            ",
            [
                'user_id_dashboard' => $userId,
                'class_id_dashboard' => $classId,
                'exam_is_active_dashboard' => 1,
            ]
        );

        return array_map(
            static fn(array $row): array => [
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
            ],
            $rows
        );
    }

    private function getStudentCompletedExams(?int $userId, int $classId): array
    {
        if (($userId ?? 0) <= 0 || $classId <= 0) {
            return [];
        }

        $rows = Database::fetchAll(
            "
            SELECT
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
                er.final_score
            FROM user_exams ue
            INNER JOIN exams e ON e.id = ue.exam_id
            LEFT JOIN exam_results er ON er.user_exam_id = ue.id
            WHERE ue.user_id = :completed_user_id
              AND ue.class_id = :completed_class_id
              AND ue.status = :completed_status
            ORDER BY ue.submitted_at DESC, ue.id DESC
            ",
            [
                'completed_user_id' => $userId,
                'completed_class_id' => $classId,
                'completed_status' => 'submitted',
            ]
        );

        return array_map(
            static fn(array $row): array => [
                'user_exam_id' => (int) ($row['user_exam_id'] ?? 0),
                'exam_id' => (int) ($row['exam_id'] ?? 0),
                'code' => (string) ($row['code'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
                'status' => (string) ($row['status'] ?? ''),
                'submitted_at' => (string) ($row['submitted_at'] ?? ''),
                'score' => (float) ($row['final_score'] ?? $row['score'] ?? 0),
            ],
            $rows
        );
    }

    private function getStudentExamByUserExamId(int $userExamId, int $userId, int $classId): ?array
    {
        if ($userExamId <= 0 || $userId <= 0 || $classId <= 0) {
            return null;
        }

        $row = Database::fetchOne(
            "
            SELECT
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
            WHERE ue.id = :student_user_exam_id_lookup
              AND ue.user_id = :student_user_id_lookup
              AND ue.class_id = :student_class_id_lookup
              AND ue.status IN ('assigned', 'started')
              AND e.is_active = :student_exam_active_lookup
            LIMIT 1
            ",
            [
                'student_user_exam_id_lookup' => $userExamId,
                'student_user_id_lookup' => $userId,
                'student_class_id_lookup' => $classId,
                'student_exam_active_lookup' => 1,
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

    private function getExamQuestions(int $userExamId): array
    {
        if ($userExamId <= 0) {
            return [];
        }

        $rows = Database::fetchAll(
            "
            SELECT
                ua.id,
                ua.user_exam_id,
                ua.question_id,
                ua.question_num,
                ua.answer_text,
                ua.correct_answer_text,
                ua.question_snapshot,
                ua.awarded_points,
                q.points
            FROM user_answers ua
            INNER JOIN questions q ON q.id = ua.question_id
            WHERE ua.user_exam_id = :student_exam_user_exam_id
            ORDER BY ua.question_num ASC, ua.id ASC
            ",
            [
                'student_exam_user_exam_id' => $userExamId,
            ]
        );

        $result = [];

        foreach ($rows as $row) {
            $snapshotRaw = (string) ($row['question_snapshot'] ?? '');
            $snapshot = [];

            if ($snapshotRaw !== '') {
                try {
                    $decoded = json_decode($snapshotRaw, true, 512, JSON_THROW_ON_ERROR);
                    if (is_array($decoded)) {
                        $snapshot = $decoded;
                    }
                } catch (\Throwable) {
                    $snapshot = [];
                }
            }

            $result[] = [
                'id' => (int) ($row['id'] ?? 0),
                'user_exam_id' => (int) ($row['user_exam_id'] ?? 0),
                'question_id' => (int) ($row['question_id'] ?? 0),
                'question_num' => (int) ($row['question_num'] ?? 0),
                'answer_text' => isset($row['answer_text']) ? (string) $row['answer_text'] : '',
                'correct_answer_text' => (string) ($row['correct_answer_text'] ?? ''),
                'awarded_points' => (float) ($row['awarded_points'] ?? 0),
                'points' => (float) ($row['points'] ?? 0),
                'snapshot' => $snapshot,
            ];
        }

        return $result;
    }

    private function evaluateStudentAnswer(
        int $userAnswerRowId,
        string $type,
        array $snapshot,
        float $questionPoints,
        array $answers,
        array $answersMulti
    ): array {
        $debugFields = [];
        $storedAnswerText = '';
        $score = 0.0;
        $isAnswered = false;
        $isCorrect = false;
        $expectedDebug = '';

        if (in_array($type, ['lists', 'schema'], true)) {
            $value = isset($answers[$userAnswerRowId]) ? trim((string) $answers[$userAnswerRowId]) : '';
            $storedAnswerText = $value;
            $isAnswered = ($value !== '');

            $correctOptions = [];
            foreach (($snapshot['options'] ?? []) as $option) {
                if (!is_array($option)) {
                    continue;
                }

                if (!empty($option['correct'])) {
                    $correctOptions[] = trim((string) ($option['text'] ?? ''));
                }
            }

            $expectedDebug = implode(' | ', $correctOptions);

            if ($isAnswered && in_array($value, $correctOptions, true)) {
                $score = $questionPoints;
                $isCorrect = true;
            }
        } elseif ($type === 'input') {
            $value = isset($answers[$userAnswerRowId]) ? trim((string) $answers[$userAnswerRowId]) : '';
            $storedAnswerText = $value;
            $isAnswered = ($value !== '');

            $expected = trim((string) ($snapshot['expected_text'] ?? ''));
            $expectedDebug = $expected;

            $correctionMode = (string) ($snapshot['correction_mode'] ?? '');
            $pointsPerChar = (float) ($snapshot['points_per_char'] ?? 1);
            $normalizer = (string) ($snapshot['normalizer'] ?? '');

            if ($isAnswered) {
                if ($correctionMode === 'per_character_position') {
                    $expectedChars = preg_split('//u', $expected, -1, PREG_SPLIT_NO_EMPTY);
                    $actualChars = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);

                    if ($expectedChars === false) {
                        $expectedChars = [];
                    }

                    if ($actualChars === false) {
                        $actualChars = [];
                    }

                    $max = count($expectedChars);
                    $good = 0;

                    for ($i = 0; $i < $max; $i++) {
                        $expectedChar = (string) ($expectedChars[$i] ?? '');
                        $actualChar = (string) ($actualChars[$i] ?? '');

                        if ($actualChar !== '' && $actualChar === $expectedChar) {
                            $good++;
                        }
                    }

                    $score = $good * $pointsPerChar;
                    $isCorrect = ($value === $expected);
                } elseif ($correctionMode === 'item_list_flexible') {
                    $caseSensitive = (bool) ($snapshot['case_sensitive'] ?? false);
                    $deduplicate = (bool) ($snapshot['deduplicate'] ?? true);
                    $correctionPolicy = (string) ($snapshot['correction_policy'] ?? 'lenient');

                    $expectedItems = isset($snapshot['expected_items']) && is_array($snapshot['expected_items'])
                        ? array_values(array_filter(array_map(
                            fn($item): string => $this->normalizeCorrectionItem((string) $item, $caseSensitive),
                            $snapshot['expected_items']
                        )))
                        : [];

                    $expectedDebug = json_encode($expectedItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';

                    $studentItems = $this->splitFlexibleAnswerItems($value, $caseSensitive, $deduplicate);

                    $pointsPerItem = (float) ($snapshot['points_per_item'] ?? 5);
                    $maxScore = (float) ($snapshot['max_score'] ?? 20);

                    $good = 0;
                    $matched = [];

                    foreach ($studentItems as $studentItem) {
                        foreach ($expectedItems as $index => $expectedItem) {
                            if (isset($matched[$index])) {
                                continue;
                            }

                            if ($studentItem === $expectedItem) {
                                $matched[$index] = true;
                                $good++;
                                break;
                            }
                        }
                    }

                    $score = min($good * $pointsPerItem, $maxScore);

                    if ($correctionPolicy === 'strict') {
                        $isCorrect = ($good === count($expectedItems) && count($expectedItems) > 0);
                    } else {
                        $isCorrect = ($good >= min(count($expectedItems), (int) floor($maxScore / max($pointsPerItem, 1))));
                    }
                } elseif ($correctionMode === 'normalized_exact') {
                    $normalizedActual = $this->normalizeExactInputValue($value, $normalizer);
                    $normalizedExpected = $this->normalizeExactInputValue($expected, $normalizer);

                    $expectedDebug = $normalizedExpected;

                    if ($normalizedActual === $normalizedExpected && $normalizedExpected !== '') {
                        $score = $questionPoints;
                        $isCorrect = true;
                    }
                } elseif ($correctionMode === 'normalized_exact_any') {
                    $expectedAny = isset($snapshot['expected_any']) && is_array($snapshot['expected_any'])
                        ? array_values(array_filter(array_map(
                            fn($item): string => $this->normalizeExactInputValue((string) $item, $normalizer),
                            $snapshot['expected_any']
                        )))
                        : [];

                    $normalizedActual = $this->normalizeExactInputValue($value, $normalizer);
                    $expectedDebug = json_encode($expectedAny, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';

                    if ($normalizedActual !== '' && in_array($normalizedActual, $expectedAny, true)) {
                        $score = $questionPoints;
                        $isCorrect = true;
                    }
                } else {
                    if ($expected !== '' && mb_strtolower($value) === mb_strtolower($expected)) {
                        $score = $questionPoints;
                        $isCorrect = true;
                    }
                }
            }
        } elseif ($type === 'inputs') {
            $values = isset($answersMulti[$userAnswerRowId]) && is_array($answersMulti[$userAnswerRowId])
                ? array_values(array_map(static fn($v): string => trim((string) $v), $answersMulti[$userAnswerRowId]))
                : [];

            $storedAnswerText = json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
            $isAnswered = count(array_filter($values, static fn(string $v): bool => $v !== '')) > 0;

            $expected = isset($snapshot['expected']) && is_array($snapshot['expected'])
                ? array_values(array_map(static fn($v): string => trim((string) $v), $snapshot['expected']))
                : [];

            $expectedDebug = json_encode($expected, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
            $pointsPerInput = (float) ($snapshot['points_per_input'] ?? 0);

            if ($expected !== []) {
                $good = 0;

                foreach ($expected as $i => $expectedValue) {
                    $actualValue = trim((string) ($values[$i] ?? ''));

                    if ($actualValue !== '' && mb_strtolower($actualValue) === mb_strtolower($expectedValue)) {
                        $good++;
                    }
                }

                $score = $good * $pointsPerInput;
                $isCorrect = ($good === count($expected) && count($expected) > 0);
            }
        } elseif ($type === 'textarea') {
            $value = isset($answers[$userAnswerRowId]) ? trim((string) $answers[$userAnswerRowId]) : '';
            $storedAnswerText = $value;
            $isAnswered = ($value !== '');
            $expectedDebug = 'Correction manuelle';
            $score = 0.0;
            $isCorrect = false;
        } elseif ($type === 'cp') {
            $rawValues = isset($answersMulti[$userAnswerRowId]) && is_array($answersMulti[$userAnswerRowId])
                ? array_values($answersMulti[$userAnswerRowId])
                : [];

            $storedAnswerText = json_encode($rawValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
            $isAnswered = count(array_filter(
                array_map(static fn($v): string => trim((string) $v), $rawValues),
                static fn(string $v): bool => $v !== ''
            )) > 0;

            $cpEvaluation = $this->evaluateCpAnswer($rawValues, $snapshot);

            $expectedDebug = json_encode(
                $cpEvaluation['debug_expected'],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ) ?: '';

            $score = $cpEvaluation['score'];
            $isCorrect = $cpEvaluation['is_correct'];
            $debugFields = $cpEvaluation['debug_fields'] ?? [];
        }

        return [
            'stored_answer_text' => $storedAnswerText,
            'expected_debug' => $expectedDebug,
            'debug_fields' => $debugFields,
            'score' => round($score, 2),
            'is_answered' => $isAnswered,
            'is_correct' => $isCorrect,
        ];
    }

    private function normalizeExactInputValue(string $value, string $normalizer): string
    {
        return match ($normalizer) {
            'python_condition_basic' => $this->normalizePythonConditionBasic($value),
            'pascal_condition_basic' => $this->normalizePascalConditionBasic($value),
            'lower_trim' => $this->normalizeLowerTrim($value),
            'python_else_basic' => $this->normalizePythonElseBasic($value),
            'python_print_ignore_string' => $this->normalizePythonPrintIgnoreString($value),
            'pascal_write_ignore_string' => $this->normalizePascalWriteIgnoreString($value),
            'assignment_basic' => $this->normalizeAssignmentBasic($value),
            'python_condition_compact' => $this->normalizePythonConditionCompact($value),
            'pascal_condition_no_parentheses' => $this->normalizePascalConditionNoParentheses($value),
            default => trim($value),
        };
    }

    private function normalizeLowerTrim(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return mb_strtolower($value);
    }

    private function normalizePythonConditionBasic(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s*<=\s*/u', '<=', $value) ?? $value;
        $value = preg_replace('/\s*!=\s*/u', '!=', $value) ?? $value;
        $value = preg_replace('/\s*:\s*/u', ':', $value) ?? $value;
        $value = preg_replace('/\s+:/u', ':', $value) ?? $value;
        return $value;
    }

    private function normalizePascalConditionBasic(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s*<=\s*/u', '<=', $value) ?? $value;
        $value = preg_replace('/\s*<>\s*/u', '<>', $value) ?? $value;
        return $value;
    }

    private function normalizePythonElseBasic(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s*:\s*/u', ':', $value) ?? $value;
        $value = preg_replace('/\s+:/u', ':', $value) ?? $value;
        return mb_strtolower($value);
    }

    private function normalizePythonPrintIgnoreString(string $value): string
    {
        $value = trim($value);
        $value = str_replace('"', "'", $value);
        $value = preg_replace("/'[^']*'/u", "''", $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s*\(\s*/u', '(', $value) ?? $value;
        $value = preg_replace('/\s*\)\s*/u', ')', $value) ?? $value;
        $value = preg_replace('/\s*,\s*/u', ',', $value) ?? $value;
        return $value;
    }

    private function normalizePascalWriteIgnoreString(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace('"', "'", $value);
        $value = preg_replace("/'[^']*'/u", "''", $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s*\(\s*/u', '(', $value) ?? $value;
        $value = preg_replace('/\s*\)\s*/u', ')', $value) ?? $value;
        $value = preg_replace('/\s*,\s*/u', ',', $value) ?? $value;
        $value = preg_replace('/\s*;\s*/u', ';', $value) ?? $value;
        return $value;
    }

    private function normalizeAssignmentBasic(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s*=\s*/u', '=', $value) ?? $value;
        return $value;
    }

    private function normalizePythonConditionCompact(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s*>=\s*/u', '>=', $value) ?? $value;
        $value = preg_replace('/\s*<=\s*/u', '<=', $value) ?? $value;
        $value = preg_replace('/\s*\(\s*/u', '(', $value) ?? $value;
        $value = preg_replace('/\s*\)\s*/u', ')', $value) ?? $value;
        $value = preg_replace('/\)\s+and\s+\(/iu', ')and(', $value) ?? $value;
        $value = preg_replace('/\s*:\s*/u', ':', $value) ?? $value;
        $value = preg_replace('/\s+:/u', ':', $value) ?? $value;
        return $value;
    }

    private function normalizePascalConditionNoParentheses(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(['(', ')'], '', $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s*>\s*/u', '>', $value) ?? $value;
        return $value;
    }

    private function evaluateCpAnswer(array $rawValues, array $snapshot): array
    {
        $fields = isset($snapshot['cp_fields']) && is_array($snapshot['cp_fields'])
            ? array_values($snapshot['cp_fields'])
            : [];

        $rulesByTopology = isset($snapshot['cp_rules']) && is_array($snapshot['cp_rules'])
            ? $snapshot['cp_rules']
            : [];

        $blankNumericAsZero = (bool) ($snapshot['blank_numeric_as_zero'] ?? true);
        $maxScore = (float) ($snapshot['max_score'] ?? 20);

        $normalized = [];
        foreach ($fields as $index => $field) {
            $key = (string) ($field['key'] ?? ('field_' . $index));
            $kind = (string) ($field['kind'] ?? 'number');
            $raw = isset($rawValues[$index]) ? (string) $rawValues[$index] : '';

            if ($kind === 'select') {
                $normalized[$key] = trim($raw);
            } else {
                $normalized[$key] = $this->normalizeCpNumericValue($raw, $blankNumericAsZero);
            }
        }

        $topology = (string) ($normalized['topology'] ?? '');
        if ($topology === '' || !isset($rulesByTopology[$topology]) || !is_array($rulesByTopology[$topology])) {
            return [
                'score' => 0.0,
                'is_correct' => false,
                'debug_expected' => ['topology' => 'Bus | Etoile | Anneau'],
                'debug_fields' => [],
            ];
        }

        $topologyRules = $rulesByTopology[$topology];
        $score = 0.0;
        $debugExpected = ['topology' => $topology];
        $debugFields = [];
        $fieldsCorrect = true;

        foreach ($topologyRules as $fieldKey => $rule) {
            if ($fieldKey === 'constraints') {
                continue;
            }

            if (!is_array($rule)) {
                continue;
            }

            $actualValue = (float) ($normalized[$fieldKey] ?? 0);
            $points = (float) ($rule['points'] ?? 0);
            $mode = (string) ($rule['mode'] ?? '');

            $ok = $this->isCpRuleSatisfied($mode, $actualValue, $rule);

            $debugExpected[$fieldKey] = $rule;
            $debugFields[] = [
                'field' => $fieldKey,
                'actual' => $actualValue,
                'rule' => $rule,
                'ok' => $ok,
                'points' => $ok ? $points : 0.0,
            ];

            if ($ok) {
                $score += $points;
            } else {
                $fieldsCorrect = false;
            }
        }

        if (isset($topologyRules['constraints']) && is_array($topologyRules['constraints'])) {
            foreach ($topologyRules['constraints'] as $constraint) {
                if (!is_array($constraint)) {
                    continue;
                }

                $ok = $this->isCpConstraintSatisfied($constraint, $normalized);

                $debugFields[] = [
                    'field' => 'constraint',
                    'actual' => null,
                    'rule' => $constraint,
                    'ok' => $ok,
                    'points' => 0.0,
                ];

                if (!$ok) {
                    $fieldsCorrect = false;
                }
            }
        }

        $finalScore = round(min($score, $maxScore), 2);

        return [
            'score' => $finalScore,
            'is_correct' => $fieldsCorrect && $finalScore >= $maxScore,
            'debug_expected' => $debugExpected,
            'debug_fields' => $debugFields,
        ];
    }

    private function normalizeCpNumericValue(string $value, bool $blankAsZero = true): float
    {
        $value = trim($value);

        if ($value === '') {
            return $blankAsZero ? 0.0 : -INF;
        }

        if (!is_numeric($value)) {
            return 0.0;
        }

        return (float) $value;
    }

    private function isCpRuleSatisfied(string $mode, float $actualValue, array $rule): bool
    {
        return match ($mode) {
            'zero_or_blank' => abs($actualValue) < 0.00001,
            'equals' => abs($actualValue - (float) ($rule['value'] ?? 0)) < 0.00001,
            'range' => $actualValue >= (float) ($rule['min'] ?? 0)
                && $actualValue <= (float) ($rule['max'] ?? 0),
            'min' => $actualValue >= (float) ($rule['value'] ?? 0),
            default => false,
        };
    }

    private function isCpConstraintSatisfied(array $constraint, array $normalized): bool
    {
        $mode = (string) ($constraint['mode'] ?? '');

        if ($mode === 'sum_min') {
            $fields = isset($constraint['fields']) && is_array($constraint['fields'])
                ? $constraint['fields']
                : [];

            $sum = 0.0;
            foreach ($fields as $field) {
                $sum += (float) ($normalized[(string) $field] ?? 0);
            }

            return $sum >= (float) ($constraint['value'] ?? 0);
        }

        return true;
    }

    private function splitFlexibleAnswerItems(string $value, bool $caseSensitive = false, bool $deduplicate = true): array
    {
        $normalized = str_replace(
            ["\r", "\n", "\t", ';', '|', '/', '\\', '-', '_'],
            ' ',
            $value
        );

        $normalized = preg_replace('/\s*,\s*/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
        $normalized = trim($normalized);

        if ($normalized === '') {
            return [];
        }

        $parts = preg_split('/\s+/u', $normalized);
        if ($parts === false) {
            return [];
        }

        $items = [];

        foreach ($parts as $part) {
            $item = $this->normalizeCorrectionItem($part, $caseSensitive);
            if ($item !== '') {
                $items[] = $item;
            }
        }

        if ($deduplicate) {
            $items = array_values(array_unique($items));
        }

        return $items;
    }

    private function normalizeCorrectionItem(string $value, bool $caseSensitive = false): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        if (!$caseSensitive) {
            $value = mb_strtolower($value);
        }

        return $value;
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