<?php

declare(strict_types=1);

$student = $student ?? [];
$network = $network ?? [];
$activeExam = $active_exam ?? null;
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
                        <div class="info-value"><?= e((string) ($student['class_id'] ?? '')) ?></div>
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
                <h2 class="h5 mb-3">Examen actif</h2>

                <?php if (is_array($activeExam)): ?>
                    <div class="border rounded-4 p-3 bg-light-subtle">
                        <div class="fw-bold mb-2"><?= e((string) ($activeExam['title'] ?? 'Examen')) ?></div>
                        <div class="text-secondary small mb-2">Code : <?= e((string) ($activeExam['code'] ?? '')) ?></div>
                        <div class="text-secondary small mb-3">Durée : <?= e((string) ($activeExam['duration_minutes'] ?? 0)) ?> min</div>

                        <button class="btn btn-danger">
                            <i class="bi bi-play-circle me-2"></i>Commencer / reprendre
                        </button>
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
