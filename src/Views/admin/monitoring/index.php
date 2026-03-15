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
                            <th>Élève</th>
                            <th>Classe</th>
                            <th>Poste</th>
                            <th>IP</th>
                            <th>Dernière activité</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($sessions ?? []) as $session): ?>
                            <tr>
                                <td><?= e($session['student_name']) ?></td>
                                <td><?= e($session['class_name']) ?></td>
                                <td><?= e($session['computer_name']) ?></td>
                                <td><?= e($session['ip_address']) ?></td>
                                <td><?= e($session['last_activity_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($sessions)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-secondary py-3">Aucune session active.</td>
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
    <h2 class="h5 mb-3">Occupation des postes</h2>
    <div class="row g-3">
        <?php foreach (($rooms ?? []) as $item): ?>
            <div class="col-md-4 col-xl-3">
                <div class="border rounded-4 p-3 h-100">
                    <div class="fw-semibold"><?= e($item['computer_name']) ?></div>
                    <div class="small text-secondary mb-2"><?= e($item['hostname']) ?></div>
                    <?php if ($item['occupied'] && !empty($item['session'])): ?>
                        <div class="small">
                            <strong><?= e($item['session']['student_name']) ?></strong><br>
                            <?= e($item['session']['class_name']) ?><br>
                            <?= e($item['session']['ip_address']) ?>
                        </div>
                    <?php else: ?>
                        <div class="small text-secondary">Libre</div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (empty($rooms)): ?>
            <div class="col-12 text-secondary">Aucun poste trouvé.</div>
        <?php endif; ?>
    </div>
</div>