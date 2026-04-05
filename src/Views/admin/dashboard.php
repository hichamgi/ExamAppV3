<?php

declare(strict_types=1);

$stats = $stats ?? [];
$admin = $admin ?? [];
?>

<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
    <div>
        <h1 class="h2 fw-bold mb-1">Dashboard administrateur</h1>
        <p class="text-secondary mb-0">
            Vue centrale de supervision des examens et des connexions.
        </p>
    </div>

    <div class="badge rounded-pill text-bg-light border px-3 py-2">
        <i class="bi bi-person-gear me-2"></i><?= e((string) ($admin['display_name'] ?? 'Admin')) ?>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="stats-card">
            <div class="stats-icon"><i class="bi bi-activity"></i></div>
            <div class="stats-value"><?= e((string) ($stats['active_sessions'] ?? 0)) ?></div>
            <div class="stats-label">Sessions actives</div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="stats-card">
            <div class="stats-icon"><i class="bi bi-mortarboard"></i></div>
            <div class="stats-value"><?= e((string) ($stats['active_students'] ?? 0)) ?></div>
            <div class="stats-label">Élèves connectés</div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="stats-card">
            <div class="stats-icon"><i class="bi bi-exclamation-diamond"></i></div>
            <div class="stats-value"><?= e((string) ($stats['open_alerts'] ?? 0)) ?></div>
            <div class="stats-label">Alertes ouvertes</div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="stats-card">
            <div class="stats-icon"><i class="bi bi-pc-display"></i></div>
            <div class="stats-value"><?= e((string) ($stats['active_computers'] ?? 0)) ?></div>
            <div class="stats-label">Postes actifs</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="app-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0">Sessions actives</h2>
                    <span class="badge text-bg-danger">Temps réel léger</span>
                </div>

                <div id="admin-active-sessions" class="table-responsive">
                    <div class="text-secondary">Chargement...</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="app-card h-100">
            <div class="card-body">
                <h2 class="h5 mb-3">Alertes récentes</h2>
                <div id="admin-alerts">
                    <div class="text-secondary">Chargement...</div>
                </div>
                <div id="admin-heartbeat-status" class="small text-secondary">
                    Vérification session admin...
                </div>
            </div>
        </div>
    </div>
</div>

<script>
window.ExamAppPage = {
    type: 'admin-dashboard',
    heartbeatUrl: '<?= e(rtrim((string) \App\Core\Config::get('app.base_url', ''), '/') . '/api/admin/heartbeat') ?>',
    csrfHeartbeat: '<?= e((string) ($csrf_heartbeat ?? '')) ?>'
};
</script>
