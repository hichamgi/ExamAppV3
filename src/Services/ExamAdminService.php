<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use RuntimeException;

class ExamAdminService
{
    private const CLASS_TYPES = ['TCT', 'TCS', 'TCL'];
    private const RESET_USER_EXAM_STATUS = 'assigned';

    private QuestionSnapshotFactory $snapshotFactory;

    public function __construct()
    {
        $this->snapshotFactory = new QuestionSnapshotFactory();
    }

    public function listExams(): array
    {
        $rows = Database::fetchAll(
            "
            SELECT
                e.id,
                e.code,
                e.title,
                e.duration_minutes,
                e.is_active,
                e.allow_print,
                e.metadata,
                e.created_at,
                e.updated_at,
                COUNT(DISTINCT q.id) AS questions_count,
                COUNT(DISTINCT ue.id) AS participants_count
            FROM exams e
            LEFT JOIN questions q ON q.exam_id = e.id
            LEFT JOIN user_exams ue ON ue.exam_id = e.id
            GROUP BY
                e.id,
                e.code,
                e.title,
                e.duration_minutes,
                e.is_active,
                e.allow_print,
                e.metadata,
                e.created_at,
                e.updated_at
            ORDER BY e.id ASC
            "
        );

        return array_map(fn(array $row): array => $this->normalizeExam($row), $rows);
    }

    public function findExamById(int $examId): ?array
    {
        if ($examId <= 0) {
            return null;
        }

        $row = Database::fetchOne(
            "
            SELECT
                e.id,
                e.code,
                e.title,
                e.duration_minutes,
                e.is_active,
                e.allow_print,
                e.metadata,
                e.created_at,
                e.updated_at,
                COUNT(DISTINCT q.id) AS questions_count,
                COUNT(DISTINCT ue.id) AS participants_count
            FROM exams e
            LEFT JOIN questions q ON q.exam_id = e.id
            LEFT JOIN user_exams ue ON ue.exam_id = e.id
            WHERE e.id = :exam_id_find
            GROUP BY
                e.id,
                e.code,
                e.title,
                e.duration_minutes,
                e.is_active,
                e.allow_print,
                e.metadata,
                e.created_at,
                e.updated_at
            LIMIT 1
            ",
            [
                'exam_id_find' => $examId,
            ]
        );

        return $row ? $this->normalizeExam($row) : null;
    }

    public function toggleExamActive(int $examId, bool $active): int
    {
        if ($examId <= 0) {
            return 0;
        }

        return Database::execute(
            "
            UPDATE exams
            SET is_active = :is_active_toggle,
                updated_at = NOW()
            WHERE id = :exam_id_toggle
            ",
            [
                'exam_id_toggle' => $examId,
                'is_active_toggle' => $active ? 1 : 0,
            ]
        );
    }

    public function toggleExamPrint(int $examId, bool $allowPrint): int
    {
        if ($examId <= 0) {
            return 0;
        }

        return Database::execute(
            "
            UPDATE exams
            SET allow_print = :allow_print_toggle,
                updated_at = NOW()
            WHERE id = :exam_id_toggle_print
            ",
            [
                'exam_id_toggle_print' => $examId,
                'allow_print_toggle' => $allowPrint ? 1 : 0,
            ]
        );
    }

    public function getExamResults(int $examId, ?int $classId = null): array
    {
        $where = ["ue.exam_id = :result_exam_id"];
        $params = [
            'result_exam_id' => $examId,
        ];

        if ($classId !== null && $classId > 0) {
            $where[] = "ue.class_id = :result_class_id";
            $params['result_class_id'] = $classId;
        }

        $rows = Database::fetchAll(
            "
            SELECT
                ue.id AS user_exam_id,
                ue.user_id,
                ue.class_id,
                ue.is_absent,
                ue.is_retake,
                ue.is_cheat,
                ue.score,
                ue.started_at,
                ue.submitted_at,
                ue.duration_seconds,
                ue.status,
                u.numero,
                u.code_massar,
                u.nom,
                u.prenom,
                c.name AS class_name,
                er.total_questions,
                er.answered_questions,
                er.correct_questions,
                er.wrong_questions,
                er.blank_questions,
                er.final_score
            FROM user_exams ue
            INNER JOIN users u ON u.id = ue.user_id
            INNER JOIN classes c ON c.id = ue.class_id
            LEFT JOIN exam_results er ON er.user_exam_id = ue.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY c.name ASC, u.numero ASC, u.nom ASC, u.prenom ASC
            ",
            $params
        );

        return array_map(
            static fn(array $row): array => [
                'user_exam_id' => (int) $row['user_exam_id'],
                'user_id' => (int) $row['user_id'],
                'class_id' => (int) $row['class_id'],
                'is_absent' => (bool) $row['is_absent'],
                'is_retake' => (bool) $row['is_retake'],
                'is_cheat' => (bool) ($row['is_cheat'] ?? false),
                'score' => (float) ($row['score'] ?? 0),
                'started_at' => (string) ($row['started_at'] ?? ''),
                'submitted_at' => (string) ($row['submitted_at'] ?? ''),
                'duration_seconds' => (int) ($row['duration_seconds'] ?? 0),
                'status' => (string) ($row['status'] ?? ''),
                'numero' => (int) ($row['numero'] ?? 0),
                'code_massar' => (string) ($row['code_massar'] ?? ''),
                'student_name' => trim(((string) ($row['nom'] ?? '')) . ' ' . ((string) ($row['prenom'] ?? ''))),
                'class_name' => (string) ($row['class_name'] ?? ''),
                'total_questions' => (int) ($row['total_questions'] ?? 0),
                'answered_questions' => (int) ($row['answered_questions'] ?? 0),
                'correct_questions' => (int) ($row['correct_questions'] ?? 0),
                'wrong_questions' => (int) ($row['wrong_questions'] ?? 0),
                'blank_questions' => (int) ($row['blank_questions'] ?? 0),
                'final_score' => (float) ($row['final_score'] ?? 0),
            ],
            $rows
        );
    }

    public function getExamAssignmentData(int $examId): array
    {
        $exam = $this->findExamById($examId);
        if ($exam === null) {
            throw new RuntimeException('Examen introuvable.');
        }

        $metadata = $this->decodeMetadata($exam['metadata']);
        $assignment = $this->extractAssignment($metadata);

        $questionRows = Database::fetchAll(
            "
            SELECT
                q.id,
                q.exam_id,
                q.category_id,
                q.question_text,
                q.points,
                q.type,
                q.num,
                q.is_required,
                q.sort_order,
                q.metadata
            FROM questions q
            WHERE q.exam_id = :assignment_exam_id
            ORDER BY q.num ASC, q.sort_order ASC, q.id ASC
            ",
            [
                'assignment_exam_id' => $examId,
            ]
        );

        $answerRows = Database::fetchAll(
            "
            SELECT
                ao.id,
                ao.question_id,
                ao.answer_text,
                ao.is_correct,
                ao.explanation,
                ao.sort_order
            FROM answer_options ao
            INNER JOIN questions q ON q.id = ao.question_id
            WHERE q.exam_id = :answers_exam_id
            ORDER BY ao.question_id ASC, ao.sort_order ASC, ao.id ASC
            ",
            [
                'answers_exam_id' => $examId,
            ]
        );

        $answersByQuestionId = [];
        foreach ($answerRows as $answerRow) {
            $questionId = (int) ($answerRow['question_id'] ?? 0);
            if ($questionId <= 0) {
                continue;
            }

            $answersByQuestionId[$questionId][] = [
                'id' => (int) ($answerRow['id'] ?? 0),
                'answer_text' => (string) ($answerRow['answer_text'] ?? ''),
                'is_correct' => (bool) ($answerRow['is_correct'] ?? false),
                'explanation' => (string) ($answerRow['explanation'] ?? ''),
                'sort_order' => (int) ($answerRow['sort_order'] ?? 0),
            ];
        }

        $grouped = [];
        foreach ($questionRows as $row) {
            $groupNum = (string) ((int) ($row['num'] ?? 0));
            if ($groupNum === '0') {
                continue;
            }

            if (!isset($grouped[$groupNum])) {
                $grouped[$groupNum] = [
                    'group_num' => (int) $groupNum,
                    'points' => (float) ($row['points'] ?? 0),
                    'available_count' => 0,
                    'questions' => [],
                ];
            }

            $questionId = (int) $row['id'];

            $grouped[$groupNum]['available_count']++;
            $grouped[$groupNum]['questions'][] = [
                'id' => $questionId,
                'question_text' => (string) ($row['question_text'] ?? ''),
                'points' => (float) ($row['points'] ?? 0),
                'type' => (string) ($row['type'] ?? ''),
                'sort_order' => (int) ($row['sort_order'] ?? 0),
                'is_required' => (bool) ($row['is_required'] ?? false),
                'category_id' => isset($row['category_id']) ? (int) $row['category_id'] : null,
                'metadata_array' => $this->decodeMetadata((string) ($row['metadata'] ?? '')),
                'answer_options' => $answersByQuestionId[$questionId] ?? [],
            ];
        }

        ksort($grouped, SORT_NUMERIC);

        $rows = [];
        $totals = [
            'TCT' => 0.0,
            'TCS' => 0.0,
            'TCL' => 0.0,
        ];

        foreach ($grouped as $groupNum => $group) {
            $assigned = [];

            foreach (self::CLASS_TYPES as $classType) {
                $value = (int) ($assignment[$classType][$groupNum] ?? 0);

                if ($value < 0) {
                    $value = 0;
                }

                if ($value > $group['available_count']) {
                    $value = $group['available_count'];
                }

                $assigned[$classType] = $value;
                $totals[$classType] += ((float) $group['points']) * $value;
            }

            $rows[] = [
                'group_num' => (int) $group['group_num'],
                'points' => (float) $group['points'],
                'available_count' => (int) $group['available_count'],
                'assigned' => $assigned,
                'questions' => $group['questions'],
            ];
        }

        return [
            'metadata' => $metadata,
            'assignment' => $assignment,
            'rows' => $rows,
            'totals' => $totals,
        ];
    }

    public function saveExamAssignment(int $examId, array $input): void
    {
        if ($examId <= 0) {
            throw new RuntimeException('Examen invalide.');
        }

        $exam = $this->findExamById($examId);
        if ($exam === null) {
            throw new RuntimeException('Examen introuvable.');
        }

        $metadata = $this->decodeMetadata($exam['metadata']);

        $questionRows = Database::fetchAll(
            "
            SELECT
                q.id,
                q.num
            FROM questions q
            WHERE q.exam_id = :save_exam_id
            ORDER BY q.num ASC, q.id ASC
            ",
            [
                'save_exam_id' => $examId,
            ]
        );

        $availableByNum = [];

        foreach ($questionRows as $row) {
            $groupNum = (string) ((int) ($row['num'] ?? 0));
            if ($groupNum === '0') {
                continue;
            }

            if (!isset($availableByNum[$groupNum])) {
                $availableByNum[$groupNum] = [
                    'available_count' => 0,
                ];
            }

            $availableByNum[$groupNum]['available_count']++;
        }

        $assignment = [
            'TCT' => [],
            'TCS' => [],
            'TCL' => [],
        ];

        foreach (self::CLASS_TYPES as $classType) {
            foreach ($availableByNum as $groupNum => $meta) {
                $fieldName = 'assign_' . $classType . '_' . $groupNum;
                $rawValue = $input[$fieldName] ?? 0;

                $value = is_scalar($rawValue) ? (int) $rawValue : 0;

                if ($value < 0) {
                    $value = 0;
                }

                $maxAvailable = (int) $meta['available_count'];
                if ($value > $maxAvailable) {
                    $value = $maxAvailable;
                }

                $assignment[$classType][$groupNum] = $value;
            }
        }

        $groupPointsInput = $input['group_points'] ?? [];
        if (!is_array($groupPointsInput)) {
            $groupPointsInput = [];
        }

        foreach ($groupPointsInput as $groupNumRaw => $pointsRaw) {
            $groupNum = (int) $groupNumRaw;
            if ($groupNum <= 0) {
                continue;
            }

            $normalized = is_scalar($pointsRaw)
                ? str_replace(',', '.', trim((string) $pointsRaw))
                : '';

            if ($normalized === '' || !is_numeric($normalized)) {
                continue;
            }

            $points = round((float) $normalized, 2);

            if ($points < 0) {
                $points = 0;
            }

            if ($points > 100) {
                $points = 100;
            }

            Database::execute(
                "
                UPDATE questions
                SET points = :points_update,
                    updated_at = NOW()
                WHERE exam_id = :exam_id_update
                  AND num = :group_num_update
                ",
                [
                    'points_update' => number_format($points, 2, '.', ''),
                    'exam_id_update' => $examId,
                    'group_num_update' => $groupNum,
                ]
            );
        }

        $metadata['question_assignment'] = $this->normalizeAssignment($assignment);

        if (!isset($metadata['idmodule']) && isset($metadata['legacy_idmodule'])) {
            $metadata['idmodule'] = (int) $metadata['legacy_idmodule'];
        }

        if (!array_key_exists('division_id', $metadata)) {
            $metadata['division_id'] = null;
        }

        $metadata = $this->decodeHtmlEntitiesRecursive($metadata);

        Database::execute(
            "
            UPDATE exams
            SET metadata = :metadata_save,
                updated_at = NOW()
            WHERE id = :exam_id_save
            ",
            [
                'metadata_save' => $this->encodeMetadata($metadata),
                'exam_id_save' => $examId,
            ]
        );
    }

    public function getExamGenerationPanelData(int $examId): array
    {
        $students = Database::fetchAll(
            "
            SELECT
                u.id AS user_id,
                c.id AS class_id,
                c.name AS class_name,
                u.numero,
                u.code_massar,
                u.nom,
                u.prenom,
                ue.id AS user_exam_id,
                ue.status,
                ue.is_absent,
                ue.is_retake,
                ue.is_cheat,
                CASE
                    WHEN ue.id IS NOT NULL AND EXISTS (
                        SELECT 1
                        FROM user_answers ua
                        WHERE ua.user_exam_id = ue.id
                    ) THEN 1 ELSE 0
                END AS has_generated_subject
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            INNER JOIN class_students cs ON cs.user_id = u.id
            INNER JOIN classes c ON c.id = cs.class_id
            LEFT JOIN user_exams ue
                ON ue.user_id = u.id
               AND ue.exam_id = :panel_exam_id
            WHERE r.code = :panel_role_code
              AND u.is_active = :panel_user_active
              AND u.numero > :panel_min_numero
            ORDER BY c.name ASC, u.numero ASC, u.nom ASC, u.prenom ASC
            ",
            [
                'panel_exam_id' => $examId,
                'panel_role_code' => 'student',
                'panel_user_active' => 1,
                'panel_min_numero' => 0,
            ]
        );

        $classBuckets = [];
        $generatedCount = 0;

        foreach ($students as $student) {
            $classId = (int) ($student['class_id'] ?? 0);
            $className = (string) ($student['class_name'] ?? '');
            $hasGenerated = (bool) ($student['has_generated_subject'] ?? false);

            if (!isset($classBuckets[$classId])) {
                $classBuckets[$classId] = [
                    'class_id' => $classId,
                    'class_name' => $className,
                    'total_students' => 0,
                    'generated_students' => 0,
                ];
            }

            $classBuckets[$classId]['total_students']++;

            if ($hasGenerated) {
                $classBuckets[$classId]['generated_students']++;
                $generatedCount++;
            }
        }

        $classSummaries = [];
        foreach ($classBuckets as $bucket) {
            $bucket['pending_students'] = max(0, $bucket['total_students'] - $bucket['generated_students']);
            $classSummaries[] = $bucket;
        }

        usort(
            $classSummaries,
            static fn(array $a, array $b): int => strcmp((string) $a['class_name'], (string) $b['class_name'])
        );

        return [
            'summary' => [
                'total_user_exams' => count($students),
                'generated_user_exams' => $generatedCount,
                'pending_user_exams' => max(0, count($students) - $generatedCount),
            ],
            'students' => array_map(
                static fn(array $row): array => [
                    'user_exam_id' => isset($row['user_exam_id']) ? (int) $row['user_exam_id'] : 0,
                    'user_id' => (int) $row['user_id'],
                    'class_id' => (int) $row['class_id'],
                    'class_name' => (string) ($row['class_name'] ?? ''),
                    'status' => (string) ($row['status'] ?? ''),
                    'is_absent' => (bool) ($row['is_absent'] ?? false),
                    'is_retake' => (bool) ($row['is_retake'] ?? false),
                    'is_cheat' => (bool) ($row['is_cheat'] ?? false),
                    'numero' => (int) ($row['numero'] ?? 0),
                    'code_massar' => (string) ($row['code_massar'] ?? ''),
                    'student_name' => trim(((string) ($row['nom'] ?? '')) . ' ' . ((string) ($row['prenom'] ?? ''))),
                    'has_generated_subject' => (bool) ($row['has_generated_subject'] ?? false),
                ],
                $students
            ),
            'classes' => $classSummaries,
        ];
    }

    public function generateExamSubjects(int $examId, ?int $classId = null): array
    {
        if ($examId <= 0) {
            throw new RuntimeException('Examen invalide.');
        }

        $exam = $this->findExamById($examId);
        if ($exam === null) {
            throw new RuntimeException('Examen introuvable.');
        }

        $targets = $this->findEligibleStudentsForGeneration($classId);
        $poolByNum = $this->getQuestionPoolByNum($examId);

        $stats = [
            'generated' => 0,
            'skipped' => 0,
        ];

        foreach ($targets as $target) {
            $classType = $this->resolveClassType((string) ($target['class_name'] ?? ''));
            if ($classType === null) {
                $stats['skipped']++;
                continue;
            }

            $assignment = $this->getAssignmentForClassType($examId, $classType);
            if ($this->isEmptyAssignment($assignment)) {
                $stats['skipped']++;
                continue;
            }

            Database::transaction(function () use ($examId, $target, $assignment, $poolByNum, &$stats): void {
                $userId = (int) $target['user_id'];
                $classId = (int) $target['class_id'];

                $userExamId = $this->findOrCreateUserExam($examId, $userId, $classId);

                $existingCountRow = Database::fetchOne(
                    "
                    SELECT COUNT(*) AS cnt
                    FROM user_answers ua
                    WHERE ua.user_exam_id = :check_user_exam_id
                    ",
                    [
                        'check_user_exam_id' => $userExamId,
                    ]
                );

                $alreadyGenerated = (int) ($existingCountRow['cnt'] ?? 0);

                if ($alreadyGenerated > 0) {
                    $stats['skipped']++;
                    return;
                }

                $this->generateSubjectRowsForUserExam(
                    $userExamId,
                    $assignment,
                    $poolByNum,
                    [
                        'class_name' => (string) ($target['class_name'] ?? ''),
                        'class_id' => (int) ($target['class_id'] ?? 0),
                        'user_id' => (int) ($target['user_id'] ?? 0),
                    ]
                );

                $stats['generated']++;
            });
        }

        return $stats;
    }

    public function regenerateStudentExam(int $examId, int $userId): void
    {
        if ($examId <= 0) {
            throw new RuntimeException('Examen invalide.');
        }

        if ($userId <= 0) {
            throw new RuntimeException('Élève invalide.');
        }

        $target = Database::fetchOne(
            "
            SELECT
                ue.id AS user_exam_id,
                ue.user_id,
                ue.class_id,
                c.name AS class_name
            FROM user_exams ue
            INNER JOIN classes c ON c.id = ue.class_id
            WHERE ue.exam_id = :regen_exam_id
              AND ue.user_id = :regen_user_id
            LIMIT 1
            ",
            [
                'regen_exam_id' => $examId,
                'regen_user_id' => $userId,
            ]
        );

        if ($target === null) {
            throw new RuntimeException('Affectation examen/élève introuvable.');
        }

        $classType = $this->resolveClassType((string) ($target['class_name'] ?? ''));
        if ($classType === null) {
            throw new RuntimeException('Type de classe non reconnu.');
        }

        $assignment = $this->getAssignmentForClassType($examId, $classType);
        if ($this->isEmptyAssignment($assignment)) {
            throw new RuntimeException('Aucune affectation de questions pour ce type de classe.');
        }

        $poolByNum = $this->getQuestionPoolByNum($examId);
        $userExamId = (int) $target['user_exam_id'];

        Database::transaction(function () use ($userExamId, $examId, $assignment, $poolByNum, $target): void {
            Database::delete('user_answers', 'user_exam_id = :delete_user_exam_id', [
                'delete_user_exam_id' => $userExamId,
            ]);

            Database::delete('exam_results', 'user_exam_id = :delete_result_user_exam_id', [
                'delete_result_user_exam_id' => $userExamId,
            ]);

            Database::update(
                'user_exams',
                [
                    'is_absent' => 1,
                    'is_retake' => 1,
                    'is_cheat' => 0,
                    'started_at' => null,
                    'submitted_at' => null,
                    'duration_seconds' => 0,
                    'score' => 0,
                    'status' => self::RESET_USER_EXAM_STATUS,
                ],
                'id = :reset_user_exam_id AND exam_id = :reset_exam_id',
                [
                    'reset_user_exam_id' => $userExamId,
                    'reset_exam_id' => $examId,
                ]
            );

            $this->generateSubjectRowsForUserExam(
                $userExamId,
                $assignment,
                $poolByNum,
                [
                    'class_name' => (string) ($target['class_name'] ?? ''),
                    'class_id' => (int) ($target['class_id'] ?? 0),
                    'user_id' => (int) ($target['user_id'] ?? 0),
                ]
            );
        });
    }

    public function buildSemesterCsv(string $semester): string
    {
        $ranges = [
            's1' => [1, 2, 3, 4, 5, 6],
            's2' => [7, 8, 9, 10, 11, 12],
        ];

        if (!isset($ranges[$semester])) {
            return '';
        }

        $examIds = $ranges[$semester];
        $selectNotes = [];
        $params = [];

        foreach ($examIds as $examId) {
            $noteColumn = 'note_' . $examId;

            $examParamCheat = 'exam_' . $examId . '_cheat';
            $examParamAbsent = 'exam_' . $examId . '_absent';
            $examParamScore = 'exam_' . $examId . '_score';

            $selectNotes[] = "
                MAX(
                    CASE
                        WHEN ue.exam_id = :{$examParamCheat} AND ue.is_cheat = 1 THEN 'T'
                        WHEN ue.exam_id = :{$examParamAbsent} AND ue.is_absent = 1 THEN 'A'
                        WHEN ue.exam_id = :{$examParamScore} THEN CAST(COALESCE(er.final_score, ue.score, 0) AS CHAR)
                        ELSE NULL
                    END
                ) AS {$noteColumn}
            ";

            $params[$examParamCheat] = $examId;
            $params[$examParamAbsent] = $examId;
            $params[$examParamScore] = $examId;
        }

        $sql = "
            SELECT
                u.code_massar,
                " . implode(",\n", $selectNotes) . "
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            LEFT JOIN user_exams ue ON ue.user_id = u.id
            LEFT JOIN exam_results er ON er.user_exam_id = ue.id
            WHERE r.code = 'student'
              AND u.numero > 0
              AND u.is_active = 1
            GROUP BY u.id, u.code_massar
            ORDER BY u.code_massar ASC
        ";

        $rows = Database::fetchAll($sql, $params);

        $handle = fopen('php://temp', 'r+');

        fputcsv(
            $handle,
            array_merge(
                ['Code MASSAR'],
                array_map(static fn(int $id): string => 'note ' . $id, $examIds)
            ),
            ';',
            '"',
            '\\'
        );

        foreach ($rows as $row) {
            $line = [(string) ($row['code_massar'] ?? '')];

            foreach ($examIds as $examId) {
                $value = $row['note_' . $examId] ?? '';
                $line[] = $value === null ? '' : (string) $value;
            }

            fputcsv($handle, $line, ';', '"', '\\');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return "\xEF\xBB\xBF" . $csv;
    }

    private function generateSubjectRowsForUserExam(
        int $userExamId,
        array $assignment,
        array $poolByNum,
        array $context = []
    ): void {
        $selectedQuestions = [];

        foreach ($assignment as $groupNumRaw => $countRaw) {
            $groupNum = (int) $groupNumRaw;
            $count = (int) $countRaw;

            if ($groupNum <= 0 || $count <= 0) {
                continue;
            }

            $pool = $poolByNum[$groupNum] ?? [];
            if (count($pool) < $count) {
                throw new RuntimeException('Pool insuffisant pour le groupe ' . $groupNum . '.');
            }

            shuffle($pool);
            $picked = array_slice($pool, 0, $count);

            foreach ($picked as $question) {
                $selectedQuestions[] = $question;
            }
        }

        if ($selectedQuestions === []) {
            return;
        }

        shuffle($selectedQuestions);

        $displayNumber = 1;

        foreach ($selectedQuestions as $question) {
            $built = $this->snapshotFactory->build($question, $context);

            Database::insert('user_answers', [
                'user_exam_id' => $userExamId,
                'question_id' => (int) $question['id'],
                'question_num' => $displayNumber,
                'awarded_points' => 0.00,
                'answer_text' => null,
                'correct_answer_text' => (string) ($built['correct_answer_text'] ?? ''),
                'question_snapshot' => (string) ($built['snapshot_json'] ?? ''),
            ]);

            $displayNumber++;
        }
    }

    private function findEligibleStudentsForGeneration(?int $classId = null): array
    {
        $where = [
            "u.is_active = :student_active",
            "u.numero > :student_min_numero",
            "r.code = :student_role_code",
        ];

        $params = [
            'student_active' => 1,
            'student_min_numero' => 0,
            'student_role_code' => 'student',
        ];

        if ($classId !== null && $classId > 0) {
            $where[] = "c.id = :student_class_id";
            $params['student_class_id'] = $classId;
        }

        return Database::fetchAll(
            "
            SELECT
                u.id AS user_id,
                c.id AS class_id,
                c.name AS class_name,
                u.numero,
                u.code_massar,
                u.nom,
                u.prenom
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            INNER JOIN class_students cs ON cs.user_id = u.id
            INNER JOIN classes c ON c.id = cs.class_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY c.name ASC, u.numero ASC, u.nom ASC, u.prenom ASC
            ",
            $params
        );
    }

    private function findOrCreateUserExam(int $examId, int $userId, int $classId): int
    {
        $existing = Database::fetchOne(
            "
            SELECT
                ue.id
            FROM user_exams ue
            WHERE ue.exam_id = :find_exam_id
              AND ue.user_id = :find_user_id
            LIMIT 1
            ",
            [
                'find_exam_id' => $examId,
                'find_user_id' => $userId,
            ]
        );

        if ($existing !== null) {
            return (int) $existing['id'];
        }

        return (int) Database::insert('user_exams', [
            'exam_id' => $examId,
            'user_id' => $userId,
            'class_id' => $classId,
            'is_absent' => 1,
            'is_retake' => 0,
            'is_cheat' => 0,
            'started_at' => null,
            'submitted_at' => null,
            'duration_seconds' => 0,
            'score' => 0,
            'status' => self::RESET_USER_EXAM_STATUS,
        ]);
    }

    private function getAssignmentForClassType(int $examId, string $classType): array
    {
        $assignmentData = $this->getExamAssignmentData($examId);
        $assignment = $assignmentData['assignment'][$classType] ?? [];

        return is_array($assignment) ? $assignment : [];
    }

    private function getQuestionPoolByNum(int $examId): array
    {
        $questionRows = Database::fetchAll(
            "
            SELECT
                q.id,
                q.exam_id,
                q.category_id,
                q.question_text,
                q.points,
                q.type,
                q.num,
                q.is_required,
                q.sort_order,
                q.metadata
            FROM questions q
            WHERE q.exam_id = :pool_exam_id
            ORDER BY q.num ASC, q.sort_order ASC, q.id ASC
            ",
            [
                'pool_exam_id' => $examId,
            ]
        );

        $answerRows = Database::fetchAll(
            "
            SELECT
                ao.id,
                ao.question_id,
                ao.answer_text,
                ao.is_correct,
                ao.explanation,
                ao.sort_order
            FROM answer_options ao
            INNER JOIN questions q ON q.id = ao.question_id
            WHERE q.exam_id = :pool_answers_exam_id
            ORDER BY ao.question_id ASC, ao.sort_order ASC, ao.id ASC
            ",
            [
                'pool_answers_exam_id' => $examId,
            ]
        );

        $answersByQuestionId = [];
        foreach ($answerRows as $answerRow) {
            $questionId = (int) ($answerRow['question_id'] ?? 0);
            if ($questionId <= 0) {
                continue;
            }

            $answersByQuestionId[$questionId][] = [
                'id' => (int) ($answerRow['id'] ?? 0),
                'answer_text' => (string) ($answerRow['answer_text'] ?? ''),
                'is_correct' => (bool) ($answerRow['is_correct'] ?? false),
                'explanation' => (string) ($answerRow['explanation'] ?? ''),
                'sort_order' => (int) ($answerRow['sort_order'] ?? 0),
            ];
        }

        $poolByNum = [];

        foreach ($questionRows as $row) {
            $groupNum = (int) ($row['num'] ?? 0);
            if ($groupNum <= 0) {
                continue;
            }

            $questionId = (int) ($row['id'] ?? 0);

            $poolByNum[$groupNum][] = [
                'id' => $questionId,
                'exam_id' => (int) ($row['exam_id'] ?? 0),
                'category_id' => isset($row['category_id']) ? (int) $row['category_id'] : null,
                'question_text' => (string) ($row['question_text'] ?? ''),
                'points' => (float) ($row['points'] ?? 0),
                'type' => (string) ($row['type'] ?? ''),
                'num' => $groupNum,
                'is_required' => (bool) ($row['is_required'] ?? false),
                'sort_order' => (int) ($row['sort_order'] ?? 0),
                'metadata_array' => $this->decodeMetadata((string) ($row['metadata'] ?? '')),
                'answer_options' => $answersByQuestionId[$questionId] ?? [],
            ];
        }

        return $poolByNum;
    }

    private function resolveClassType(string $className): ?string
    {
        $name = strtoupper(trim($className));

        if (str_starts_with($name, 'TCT')) {
            return 'TCT';
        }

        if (str_starts_with($name, 'TCS')) {
            return 'TCS';
        }

        if (str_starts_with($name, 'TCL')) {
            return 'TCL';
        }

        return null;
    }

    private function isEmptyAssignment(array $assignment): bool
    {
        foreach ($assignment as $value) {
            if ((int) $value > 0) {
                return false;
            }
        }

        return true;
    }

    private function normalizeExam(array $row): array
    {
        $metadata = $this->decodeMetadata((string) ($row['metadata'] ?? ''));

        if (!isset($metadata['idmodule']) && isset($metadata['legacy_idmodule'])) {
            $metadata['idmodule'] = (int) $metadata['legacy_idmodule'];
        }

        return [
            'id' => (int) $row['id'],
            'code' => (string) ($row['code'] ?? ''),
            'title' => (string) ($row['title'] ?? ''),
            'duration_minutes' => (int) ($row['duration_minutes'] ?? 0),
            'is_active' => (bool) ($row['is_active'] ?? false),
            'allow_print' => (bool) ($row['allow_print'] ?? false),
            'metadata' => (string) ($row['metadata'] ?? ''),
            'metadata_array' => $metadata,
            'questions_count' => (int) ($row['questions_count'] ?? 0),
            'participants_count' => (int) ($row['participants_count'] ?? 0),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    private function decodeMetadata(?string $metadata): array
    {
        if ($metadata === null || trim($metadata) === '') {
            return [];
        }

        try {
            $decoded = json_decode($metadata, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function encodeMetadata(array $metadata): string
    {
        return json_encode(
            $metadata,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
        );
    }

    private function extractAssignment(array $metadata): array
    {
        if (isset($metadata['question_assignment']) && is_array($metadata['question_assignment'])) {
            return $this->normalizeAssignment($metadata['question_assignment']);
        }

        if (!empty($metadata['legacy_description']) && is_string($metadata['legacy_description'])) {
            return $this->extractAssignmentFromLegacyDescription($metadata['legacy_description']);
        }

        return [
            'TCT' => [],
            'TCS' => [],
            'TCL' => [],
        ];
    }

    private function extractAssignmentFromLegacyDescription(string $legacy): array
    {
        $map = [
            '1' => 'TCT',
            '2' => 'TCS',
            '3' => 'TCL',
        ];

        $assignment = [
            'TCT' => [],
            'TCS' => [],
            'TCL' => [],
        ];

        $parts = array_filter(array_map('trim', explode('|', $legacy)));

        foreach ($parts as $part) {
            $segments = explode(':', $part, 2);
            if (count($segments) !== 2) {
                continue;
            }

            $legacyClassId = trim($segments[0]);
            $classType = $map[$legacyClassId] ?? null;
            if ($classType === null) {
                continue;
            }

            $items = array_filter(array_map('trim', explode(',', $segments[1])));

            foreach ($items as $item) {
                $pair = explode('.', $item, 2);
                if (count($pair) !== 2) {
                    continue;
                }

                $groupNum = (string) ((int) $pair[0]);
                $count = max(0, (int) $pair[1]);

                if ($groupNum === '0') {
                    continue;
                }

                $assignment[$classType][$groupNum] = $count;
            }
        }

        return $this->normalizeAssignment($assignment);
    }

    private function normalizeAssignment(array $assignment): array
    {
        $normalized = [
            'TCT' => [],
            'TCS' => [],
            'TCL' => [],
        ];

        foreach (self::CLASS_TYPES as $classType) {
            $source = $assignment[$classType] ?? [];
            if (!is_array($source)) {
                continue;
            }

            foreach ($source as $groupNum => $value) {
                $groupNumKey = (string) ((int) $groupNum);
                if ($groupNumKey === '0') {
                    continue;
                }

                $normalized[$classType][$groupNumKey] = max(0, (int) $value);
            }

            ksort($normalized[$classType], SORT_NUMERIC);
        }

        return $normalized;
    }

    private function decodeHtmlEntitiesRecursive(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->decodeHtmlEntitiesRecursive($value);
                continue;
            }

            if (is_string($value)) {
                $data[$key] = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        return $data;
    }
}