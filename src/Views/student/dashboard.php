<?php

declare(strict_types=1);

$student = $student ?? [];
$network = $network ?? [];
$activeExams = is_array($active_exams ?? null) ? $active_exams : [];
$computer = is_array($network['computer'] ?? null) ? $network['computer'] : null;
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
        <div class="app-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
                    <h2 class="h5 mb-0">Examens disponibles</h2>
                    <span class="badge text-bg-light border">
                        <?= count($activeExams) ?> examen(s)
                    </span>
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