<?php

namespace App\Services;

use App\Core\Database;
use RuntimeException;

class ExamAdminService
{
    private const CLASS_TYPES = ['TCT', 'TCS', 'TCL'];

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

        $whereSql = implode(' AND ', $where);

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
            WHERE {$whereSql}
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
                'score' => (float) $row['score'],
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
                q.num,
                q.question_text,
                q.points,
                q.type,
                q.sort_order,
                q.is_required,
                q.category_id
            FROM questions q
            WHERE q.exam_id = :assignment_exam_id
            ORDER BY q.num ASC, q.sort_order ASC, q.id ASC
            ",
            [
                'assignment_exam_id' => $examId,
            ]
        );

        $grouped = [];
        foreach ($questionRows as $row) {
            $num = (string) ((int) ($row['num'] ?? 0));
            if ($num === '0') {
                continue;
            }

            if (!isset($grouped[$num])) {
                $grouped[$num] = [
                    'num' => (int) $num,
                    'points' => (float) ($row['points'] ?? 0),
                    'available_count' => 0,
                    'questions' => [],
                ];
            }

            $grouped[$num]['available_count']++;
            $grouped[$num]['questions'][] = [
                'id' => (int) $row['id'],
                'question_text' => (string) ($row['question_text'] ?? ''),
                'points' => (float) ($row['points'] ?? 0),
                'type' => (string) ($row['type'] ?? ''),
                'sort_order' => (int) ($row['sort_order'] ?? 0),
                'is_required' => (bool) ($row['is_required'] ?? false),
                'category_id' => isset($row['category_id']) ? (int) $row['category_id'] : null,
            ];
        }

        ksort($grouped, SORT_NUMERIC);

        $rows = [];
        $totals = [
            'TCT' => 0.0,
            'TCS' => 0.0,
            'TCL' => 0.0,
        ];

        foreach ($grouped as $num => $group) {
            $assigned = [];

            foreach (self::CLASS_TYPES as $classType) {
                $value = (int) ($assignment[$classType][$num] ?? 0);

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
                'num' => (int) $group['num'],
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

        $availableRows = Database::fetchAll(
            "
            SELECT
                q.num,
                COUNT(*) AS available_count,
                MAX(q.points) AS point_value
            FROM questions q
            WHERE q.exam_id = :save_exam_id
            GROUP BY q.num
            ORDER BY q.num ASC
            ",
            [
                'save_exam_id' => $examId,
            ]
        );

        $availableByNum = [];
        foreach ($availableRows as $row) {
            $num = (string) ((int) ($row['num'] ?? 0));
            if ($num === '0') {
                continue;
            }

            $availableByNum[$num] = [
                'available_count' => (int) ($row['available_count'] ?? 0),
                'points' => (float) ($row['point_value'] ?? 0),
            ];
        }

        $assignment = [
            'TCT' => [],
            'TCS' => [],
            'TCL' => [],
        ];

        foreach (self::CLASS_TYPES as $classType) {
            foreach ($availableByNum as $num => $meta) {
                $fieldName = 'assign_' . $classType . '_' . $num;
                $rawValue = $input[$fieldName] ?? 0;

                $value = is_scalar($rawValue) ? (int) $rawValue : 0;

                if ($value < 0) {
                    $value = 0;
                }

                $maxAvailable = (int) $meta['available_count'];
                if ($value > $maxAvailable) {
                    $value = $maxAvailable;
                }

                $assignment[$classType][$num] = $value;
            }
        }

        $metadata['question_assignment'] = $this->normalizeAssignment($assignment);

        if (!isset($metadata['idmodule']) && isset($metadata['legacy_idmodule'])) {
            $metadata['idmodule'] = (int) $metadata['legacy_idmodule'];
        }

        if (!array_key_exists('division_id', $metadata)) {
            $metadata['division_id'] = null;
        }

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
        $csv = stream_get_contents($handle);
        fclose($handle);

        return "\xEF\xBB\xBF" . $csv;
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
        } catch (\Throwable $e) {
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

                $num = (string) ((int) $pair[0]);
                $count = max(0, (int) $pair[1]);

                if ($num === '0') {
                    continue;
                }

                $assignment[$classType][$num] = $count;
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

            foreach ($source as $num => $value) {
                $numKey = (string) ((int) $num);
                if ($numKey === '0') {
                    continue;
                }

                $normalized[$classType][$numKey] = max(0, (int) $value);
            }

            ksort($normalized[$classType], SORT_NUMERIC);
        }

        return $normalized;
    }
}