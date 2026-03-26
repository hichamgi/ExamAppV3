<?php

declare(strict_types=1);

$student = $student ?? [];
$network = $network ?? [];
$activeExams = is_array($active_exams ?? null) ? $active_exams : [];
$completedExams = is_array($completed_exams ?? null) ? $completed_exams : [];
$studentExamDebug = $student_exam_debug ?? null;
$computer = is_array($network['computer'] ?? null) ? $network['computer'] : null;

$showDebugCorrection = (bool) \App\Core\Config::get('app.exam.debug_student_correction', false)
    && (bool) \App\Core\Config::get('app.debug', false);
?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="hero-card p-4 p-lg-5 h-100">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                <div>
                    <h1 class="h2 fw-bold mb-1">Espace élève</h1>
                    <p class="text-secondary mb-0">
                        Bonjour <?= e((string) ($student['display_name'] ?? 'Élève')) ?>.
                    </p>
                </div>

                <div class="badge rounded-pill text-bg-light border px-3 py-2">
                    <i class="bi bi-hash me-1"></i>N° <?= e((string) ($student['numero'] ?? '')) ?>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <div class="info-tile">
                        <div class="info-label">Code Massar</div>
                        <div class="info-value"><?= e((string) ($student['code_massar'] ?? '')) ?></div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="info-tile">
                        <div class="info-label">Classe</div>
                        <div class="info-value"><?= e((string) ($student['class_name'] ?? $student['class_id'] ?? '')) ?></div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="info-tile">
                        <div class="info-label">Poste</div>
                        <div class="info-value"><?= e((string) ($computer['name'] ?? 'Non détecté')) ?></div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="info-tile">
                        <div class="info-label">Connexion</div>
                        <div class="info-value text-uppercase"><?= e((string) ($network['network_type'] ?? 'unknown')) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="app-card h-100">
            <div class="card-body">
                <h2 class="h5 mb-3">État de session</h2>

                <div class="status-line mb-3">
                    <span class="status-dot status-dot-success"></span>
                    <span>Session active</span>
                </div>

                <div class="small text-secondary mb-2">IP actuelle</div>
                <div class="fw-semibold mb-3"><?= e((string) ($network['ip'] ?? '')) ?></div>

                <div class="small text-secondary mb-2">Hostname</div>
                <div class="fw-semibold"><?= e((string) ($computer['hostname'] ?? '')) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mt-1">
    <div class="col-lg-8">
        <div class="app-card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
                    <h2 class="h5 mb-0">Examens disponibles</h2>
                    <span class="badge text-bg-light border"><?= count($activeExams) ?> examen(s)</span>
                </div>

                <?php if ($activeExams !== []): ?>
                    <div class="d-grid gap-3">
                        <?php foreach ($activeExams as $exam): ?>
                            <div class="border rounded-4 p-3 bg-light-subtle">
                                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                                    <div>
                                        <div class="fw-bold mb-2"><?= e((string) ($exam['title'] ?? 'Examen')) ?></div>
                                        <div class="text-secondary small mb-1">
                                            Code : <?= e((string) ($exam['code'] ?? '')) ?>
                                        </div>
                                        <div class="text-secondary small mb-1">
                                            Durée : <?= e((string) ($exam['duration_minutes'] ?? 0)) ?> min
                                        </div>
                                        <div class="text-secondary small">
                                            Statut :
                                            <span class="fw-semibold"><?= e((string) ($exam['status'] ?? 'assigned')) ?></span>
                                        </div>
                                    </div>

                                    <div class="d-flex align-items-center">
                                        <a href="<?= e(base_url('student/exam?user_exam_id=' . (int) ($exam['user_exam_id'] ?? 0))) ?>" class="btn btn-danger">
                                            <i class="bi bi-play-circle me-2"></i>Commencer / reprendre
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-secondary mb-0">
                        Aucun examen actif pour le moment.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($showDebugCorrection && is_array($studentExamDebug)): ?>
            <div class="app-card mb-4 border border-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 mb-0 text-warning-emphasis">Debug correction examen</h2>
                        <span class="badge text-bg-warning">
                            UserExam #<?= (int) ($studentExamDebug['user_exam_id'] ?? 0) ?>
                        </span>
                    </div>

                    <?php $summary = is_array($studentExamDebug['summary'] ?? null) ? $studentExamDebug['summary'] : []; ?>
                    <div class="row g-3 mb-4">
                        <div class="col-md-2">
                            <div class="border rounded-3 p-3 bg-light">
                                <div class="small text-secondary">Total</div>
                                <div class="fw-bold"><?= (int) ($summary['total_questions'] ?? 0) ?></div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="border rounded-3 p-3 bg-light">
                                <div class="small text-secondary">Répondues</div>
                                <div class="fw-bold"><?= (int) ($summary['answered_questions'] ?? 0) ?></div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="border rounded-3 p-3 bg-light">
                                <div class="small text-secondary">Justes</div>
                                <div class="fw-bold"><?= (int) ($summary['correct_questions'] ?? 0) ?></div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="border rounded-3 p-3 bg-light">
                                <div class="small text-secondary">Fausses</div>
                                <div class="fw-bold"><?= (int) ($summary['wrong_questions'] ?? 0) ?></div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="border rounded-3 p-3 bg-light">
                                <div class="small text-secondary">Vides</div>
                                <div class="fw-bold"><?= (int) ($summary['blank_questions'] ?? 0) ?></div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="border rounded-3 p-3 bg-light">
                                <div class="small text-secondary">Note</div>
                                <div class="fw-bold"><?= e(number_format((float) ($summary['final_score'] ?? 0), 2, '.', '')) ?></div>
                            </div>
                        </div>
                    </div>

                    <?php $debugQuestions = is_array($studentExamDebug['questions'] ?? null) ? $studentExamDebug['questions'] : []; ?>
                    <?php if ($debugQuestions !== []): ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Type</th>
                                        <th>État</th>
                                        <th>Réponse élève</th>
                                        <th>Réponse attendue</th>
                                        <th class="text-end">Points</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($debugQuestions as $row): ?>
                                        <?php
                                        $state = 'Vide';
                                        $badgeClass = 'text-bg-secondary';

                                        if (!empty($row['is_correct'])) {
                                            $state = 'Juste';
                                            $badgeClass = 'text-bg-success';
                                        } elseif (!empty($row['is_answered'])) {
                                            $state = 'Fausse';
                                            $badgeClass = 'text-bg-danger';
                                        }
                                        ?>
                                        <tr>
                                            <td><?= (int) ($row['question_num'] ?? 0) ?></td>
                                            <td><?= e((string) ($row['type'] ?? '')) ?></td>
                                            <td>
                                                <span class="badge <?= e($badgeClass) ?>">
                                                    <?= e($state) ?>
                                                </span>
                                            </td>
                                            <td><?= e((string) ($row['student_answer'] ?? '')) ?></td>
                                            <td>
                                                <?= e((string) ($row['expected_answer'] ?? '')) ?>

                                                <?php if (($row['type'] ?? '') === 'cp' && !empty($row['debug_fields']) && is_array($row['debug_fields'])): ?>
                                                    <div class="mt-2 small">
                                                        <?php foreach ($row['debug_fields'] as $fieldDebug): ?>
                                                            <div class="border rounded p-2 mb-2 bg-light">
                                                                <div>
                                                                    <strong>Champ :</strong>
                                                                    <?= e((string) ($fieldDebug['field'] ?? '')) ?>
                                                                </div>

                                                                <?php if (array_key_exists('actual', $fieldDebug)): ?>
                                                                    <div>
                                                                        <strong>Valeur :</strong>
                                                                        <?= e((string) ($fieldDebug['actual'] ?? '')) ?>
                                                                    </div>
                                                                <?php endif; ?>

                                                                <div style="white-space: pre-wrap;">
                                                                    <strong>Règle :</strong>
                                                                    <?= e(json_encode($fieldDebug['rule'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '') ?>
                                                                </div>

                                                                <div>
                                                                    <strong>État :</strong>
                                                                    <?php if (!empty($fieldDebug['ok'])): ?>
                                                                        <span class="badge text-bg-success">Correct</span>
                                                                    <?php else: ?>
                                                                        <span class="badge text-bg-danger">Faux</span>
                                                                    <?php endif; ?>
                                                                </div>

                                                                <div>
                                                                    <strong>Points :</strong>
                                                                    <?= e(number_format((float) ($fieldDebug['points'] ?? 0), 2, '.', '')) ?>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end"><?= e(number_format((float) ($row['score'] ?? 0), 2, '.', '')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="app-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
                    <h2 class="h5 mb-0">Notes</h2>
                    <span class="badge text-bg-light border"><?= count($completedExams) ?> examen(s)</span>
                </div>

                <?php if ($completedExams !== []): ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Examen</th>
                                    <th>Date</th>
                                    <th class="text-end">Note</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($completedExams as $exam): ?>
                                    <tr>
                                        <td><?= e((string) ($exam['code'] ?? '')) ?></td>
                                        <td><?= e((string) ($exam['title'] ?? '')) ?></td>
                                        <td><?= e((string) ($exam['submitted_at'] ?? '')) ?></td>
                                        <td class="text-end fw-bold"><?= e(number_format((float) ($exam['score'] ?? 0), 2, '.', '')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-secondary mb-0">
                        Aucune note disponible pour le moment.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="app-card">
            <div class="card-body">
                <h2 class="h5 mb-3">Synchronisation</h2>
                <p class="text-secondary small mb-3">
                    La page peut envoyer un heartbeat léger pour maintenir la session active côté serveur.
                </p>

                <div id="student-heartbeat-status" class="small text-success">
                    Prêt
                </div>
            </div>
        </div>
    </div>
</div>

<script>
window.ExamAppPage = {
    type: 'student-dashboard',
    heartbeatUrl: '<?= e(rtrim((string) \App\Core\Config::get('app.base_url', ''), '/') . '/api/student/heartbeat') ?>',
    csrfHeartbeat: '<?= e((string) ($csrf_heartbeat ?? '')) ?>'
};
</script>