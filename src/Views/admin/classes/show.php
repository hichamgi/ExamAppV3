<h1 class="h3 mb-4">Détail classe</h1>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="app-card">
            <h2 class="h5 mb-3"><?= e($class['label']) ?></h2>
            <p class="mb-1"><strong>Actif :</strong> <?= $class['is_active'] ? 'Oui' : 'Non' ?></p>
            <p class="mb-1"><strong>Effectif :</strong> <?= e((string) $class['students_count']) ?></p>
            <p class="mb-3"><strong>Peuvent se connecter :</strong> <?= e((string) $class['can_login_count']) ?></p>

            <div class="d-grid gap-2">
                <form method="POST" action="<?= e(base_url('admin/classes/allow-login')) ?>">
                    <?= \App\Core\Csrf::input('admin.class.auth') ?>
                    <input type="hidden" name="class_id" value="<?= e((string) $class['id']) ?>">
                    <button class="btn btn-success w-100">Autoriser toute la classe</button>
                </form>

                <form method="POST" action="<?= e(base_url('admin/classes/allow-group-login')) ?>">
                    <?= \App\Core\Csrf::input('admin.class.auth') ?>
                    <input type="hidden" name="class_id" value="<?= e((string) $class['id']) ?>">
                    <input type="hidden" name="group" value="1">
                    <button class="btn btn-outline-primary w-100">Autoriser groupe 1</button>
                </form>

                <form method="POST" action="<?= e(base_url('admin/classes/allow-group-login')) ?>">
                    <?= \App\Core\Csrf::input('admin.class.auth') ?>
                    <input type="hidden" name="class_id" value="<?= e((string) $class['id']) ?>">
                    <input type="hidden" name="group" value="2">
                    <button class="btn btn-outline-primary w-100">Autoriser groupe 2</button>
                </form>

                <form method="POST" action="<?= e(base_url('admin/classes/deny-login')) ?>">
                    <?= \App\Core\Csrf::input('admin.class.auth') ?>
                    <input type="hidden" name="class_id" value="<?= e((string) $class['id']) ?>">
                    <button class="btn btn-outline-danger w-100">Bloquer toute la classe</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="app-card">
            <h2 class="h5 mb-3">Élèves</h2>

            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>N°</th>
                            <th>Nom</th>
                            <th>Code</th>
                            <th>Connexion</th>
                            <th>Compte</th>
                            <th>Sessions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($students ?? []) as $student): ?>
                            <tr>
                                <td><?= e((string) $student['numero']) ?></td>
                                <td><?= e($student['display_name']) ?></td>
                                <td><?= e($student['code_massar']) ?></td>
                                <td><?= $student['can_login'] ? 'Oui' : 'Non' ?></td>
                                <td><?= $student['is_active'] ? 'Actif' : 'Off' ?></td>
                                <td><?= e((string) $student['active_sessions']) ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-secondary py-3">Aucun élève.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>