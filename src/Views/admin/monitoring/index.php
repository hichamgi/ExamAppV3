<h1 class="h3 mb-4">Supervision</h1>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="app-card">
            <div class="fw-semibold">Sessions actives</div>
            <div class="display-6"><?= e((string) ($stats['active_sessions'] ?? 0)) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="app-card">
            <div class="fw-semibold">Élèves connectés</div>
            <div class="display-6"><?= e((string) ($stats['active_students'] ?? 0)) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="app-card">
            <div class="fw-semibold">Alertes</div>
            <div class="display-6"><?= e((string) ($stats['open_alerts'] ?? 0)) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="app-card">
            <div class="fw-semibold">Postes actifs</div>
            <div class="display-6"><?= e((string) ($stats['active_computers'] ?? 0)) ?></div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="app-card">
            <h2 class="h5 mb-3">Sessions actives</h2>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Utilisateur</th>
                            <th>Classe</th>
                            <th>Poste</th>
                            <th>IP</th>
                            <th>Dernière activité</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($sessions ?? []) as $session): ?>
                            <?php $isAdmin = (($session['role_code'] ?? '') === 'admin'); ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold">
                                        <?= e($session['display_name'] ?? '') ?>
                                    </div>
                                    <div class="small <?= $isAdmin ? 'text-primary' : 'text-secondary' ?>">
                                        <?= $isAdmin ? 'Admin' : 'Élève' ?>
                                    </div>
                                </td>
                                <td><?= e($session['class_name'] ?? '—') ?></td>
                                <td><?= e($session['computer_name'] ?? '—') ?></td>
                                <td><?= e($session['ip_address'] ?? '') ?></td>
                                <td><?= e($session['last_activity_at'] ?? '') ?></td>
                                <td class="text-end">
                                    <?php if ($isAdmin): ?>
                                        <form method="POST" action="<?= e(base_url('admin/monitoring/force-logout-ip')) ?>" class="d-inline">
                                            <input type="hidden" name="_csrf" value="<?= e($csrf_monitoring_action) ?>">
                                            <input type="hidden" name="ip_address" value="<?= e($session['ip_address'] ?? '') ?>">
                                            <input type="hidden" name="session_id" value="<?= (int) ($session['session_id'] ?? 0) ?>">

                                            <button
                                                type="submit"
                                                class="btn btn-sm btn-outline-danger"
                                                data-bs-toggle="tooltip"
                                                title="Déconnecter les autres sessions admin de cette IP"
                                            >
                                                <i class="bi bi-router"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" action="<?= e(base_url('admin/monitoring/force-logout')) ?>" class="d-inline">
                                            <input type="hidden" name="_csrf" value="<?= e($csrf_monitoring_action) ?>">
                                            <input type="hidden" name="session_id" value="<?= (int) ($session['session_id'] ?? 0) ?>">

                                            <button
                                                type="submit"
                                                class="btn btn-sm btn-outline-danger"
                                                data-bs-toggle="tooltip"
                                                title="Déconnecter cette session"
                                            >
                                                <i class="bi bi-box-arrow-right"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($sessions)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-secondary py-3">Aucune session active.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="app-card">
            <h2 class="h5 mb-3">Alertes récentes</h2>
            <div class="d-grid gap-3">
                <?php foreach (($alerts ?? []) as $alert): ?>
                    <div class="border rounded-4 p-3">
                        <div class="fw-semibold"><?= e($alert['student_name'] !== '' ? $alert['student_name'] : $alert['username_attempted']) ?></div>
                        <div class="small text-secondary mb-1">
                            <?= e($alert['existing_computer_name']) ?> → <?= e($alert['attempted_computer_name']) ?>
                        </div>
                        <div class="small">
                            <span class="badge text-bg-light border"><?= e($alert['status']) ?></span>
                            <span class="ms-2"><?= e($alert['attempted_at']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($alerts)): ?>
                    <div class="text-secondary">Aucune alerte.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="app-card mt-4">
    <h2 class="h5 mt-4">Occupation des postes</h2>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Poste</th>
                    <th>Salle</th>
                    <th>État</th>
                    <th>Élève</th>
                    <th>Classe</th>
                    <th>Dernière activité</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rooms)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted">Aucun poste trouvé.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rooms as $room): ?>
                        <?php $session = $room['session'] ?? null; ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e($room['computer_name'] ?? '') ?></div>
                                <div class="small text-muted"><?= e($room['hostname'] ?? '') ?></div>
                            </td>
                            <td><?= e($room['room_name'] ?? '') ?></td>
                            <td>
                                <?php if (!empty($room['occupied'])): ?>
                                    <span class="badge text-bg-danger">Occupé</span>
                                <?php else: ?>
                                    <span class="badge text-bg-success">Libre</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($session): ?>
                                    <div class="fw-semibold"><?= e($session['student_name'] ?? '') ?></div>
                                    <div class="small text-muted">N° <?= (int) ($session['numero'] ?? 0) ?></div>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($session['class_name'] ?? '—') ?></td>
                            <td><?= e($session['last_activity_at'] ?? '—') ?></td>
                            <td class="text-end">
                                <?php if ($session): ?>
                                    <div class="btn-group btn-group-sm d-inline-flex" role="group">

                                        <form method="POST" action="<?= e(base_url('admin/monitoring/force-logout')) ?>" class="d-inline">
                                            <input type="hidden" name="_csrf" value="<?= e($csrf_monitoring_action) ?>">
                                            <input type="hidden" name="session_id" value="<?= (int) $session['session_id'] ?>">
                                            <button
                                                type="submit"
                                                class="btn btn-outline-danger"
                                                data-bs-toggle="tooltip"
                                                title="Forcer la déconnexion"
                                            >
                                                <i class="bi bi-box-arrow-right"></i>
                                            </button>
                                        </form>

                                        <form method="POST" action="<?= e(base_url('admin/monitoring/block-student')) ?>" class="d-inline">
                                            <input type="hidden" name="_csrf" value="<?= e($csrf_monitoring_action) ?>">
                                            <input type="hidden" name="session_id" value="<?= (int) $session['session_id'] ?>">
                                            <button
                                                type="submit"
                                                class="btn btn-outline-warning"
                                                data-bs-toggle="tooltip"
                                                title="Bloquer l'élève"
                                            >
                                                <i class="bi bi-person-lock"></i>
                                            </button>
                                        </form>

                                        <form method="POST" action="<?= e(base_url('admin/monitoring/mark-cheat')) ?>" class="d-inline">
                                            <input type="hidden" name="_csrf" value="<?= e($csrf_monitoring_action) ?>">
                                            <input type="hidden" name="session_id" value="<?= (int) $session['session_id'] ?>">
                                            <button
                                                type="submit"
                                                class="btn btn-outline-dark"
                                                data-bs-toggle="tooltip"
                                                title="Déclarer triche sur le contrôle en cours"
                                            >
                                                <i class="bi bi-exclamation-octagon"></i>
                                            </button>
                                        </form>

                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>