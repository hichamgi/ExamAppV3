<h1 class="h3 mb-4">Autorisations de connexion</h1>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="app-card">
            <h2 class="h5 mb-3">Autorisations par classe</h2>

            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Classe</th>
                            <th>Autorisés</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($classes ?? []) as $class): ?>
                            <tr>
                                <td><?= e($class['label']) ?></td>
                                <td><?= e((string) $class['can_login_count']) ?></td>
                                <td class="text-end">
                                    <form method="POST" action="<?= e(base_url('admin/classes/allow-login')) ?>" class="d-inline">
                                        <?= \App\Core\Csrf::input('admin.authorization') ?>
                                        <input type="hidden" name="class_id" value="<?= e((string) $class['id']) ?>">
                                        <button class="btn btn-sm btn-outline-success">Autoriser</button>
                                    </form>
                                    <form method="POST" action="<?= e(base_url('admin/classes/deny-login')) ?>" class="d-inline">
                                        <?= \App\Core\Csrf::input('admin.authorization') ?>
                                        <input type="hidden" name="class_id" value="<?= e((string) $class['id']) ?>">
                                        <button class="btn btn-sm btn-outline-danger">Bloquer</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($classes)): ?>
                            <tr>
                                <td colspan="3" class="text-center text-secondary py-3">Aucune classe.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="app-card">
            <h2 class="h5 mb-3">Autorisations individuelles</h2>

            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Élève</th>
                            <th>Classe</th>
                            <th>Connexion</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($students['items'] ?? []) as $student): ?>
                            <tr>
                                <td><?= e($student['display_name']) ?></td>
                                <td><?= e($student['class_name']) ?></td>
                                <td><?= $student['can_login'] ? 'Oui' : 'Non' ?></td>
                                <td class="text-end">
                                    <?php if ($student['can_login']): ?>
                                        <form method="POST" action="<?= e(base_url('admin/authorizations/deny-student')) ?>" class="d-inline">
                                            <?= \App\Core\Csrf::input('admin.authorization') ?>
                                            <input type="hidden" name="user_id" value="<?= e((string) $student['id']) ?>">
                                            <button class="btn btn-sm btn-outline-danger">Bloquer</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" action="<?= e(base_url('admin/authorizations/allow-student')) ?>" class="d-inline">
                                            <?= \App\Core\Csrf::input('admin.authorization') ?>
                                            <input type="hidden" name="user_id" value="<?= e((string) $student['id']) ?>">
                                            <button class="btn btn-sm btn-outline-success">Autoriser</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($students['items'] ?? [])): ?>
                            <tr>
                                <td colspan="4" class="text-center text-secondary py-3">Aucun élève.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>