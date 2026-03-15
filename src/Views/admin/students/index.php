<?php
$items = $students['items'] ?? [];
$meta = $students['meta'] ?? [];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Gestion des élèves</h1>
        <p class="text-secondary mb-0">Recherche, blocage, autorisation de connexion et déconnexion forcée.</p>
    </div>
</div>

<div class="app-card mb-4">
    <form method="GET" action="<?= e(base_url('admin/students')) ?>" class="row g-3">
        <div class="col-md-4">
            <label class="form-label">Recherche</label>
            <input type="text" name="search" class="form-control" value="<?= e((string) ($filters['search'] ?? '')) ?>">
        </div>

        <div class="col-md-3">
            <label class="form-label">Classe</label>
            <select name="class_id" class="form-select">
                <option value="">Toutes</option>
                <?php foreach (($classes ?? []) as $class): ?>
                    <option value="<?= e((string) $class['id']) ?>" <?= ((int) ($filters['class_id'] ?? 0) === (int) $class['id']) ? 'selected' : '' ?>>
                        <?= e($class['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-2">
            <label class="form-label">Connexion</label>
            <select name="can_login" class="form-select">
                <option value="">Tous</option>
                <option value="1" <?= ((string) ($filters['can_login'] ?? '')) === '1' ? 'selected' : '' ?>>Autorisés</option>
                <option value="0" <?= ((string) ($filters['can_login'] ?? '')) === '0' ? 'selected' : '' ?>>Bloqués</option>
            </select>
        </div>

        <div class="col-md-2">
            <label class="form-label">Statut</label>
            <select name="is_active" class="form-select">
                <option value="">Tous</option>
                <option value="1" <?= ((string) ($filters['is_active'] ?? '')) === '1' ? 'selected' : '' ?>>Actifs</option>
                <option value="0" <?= ((string) ($filters['is_active'] ?? '')) === '0' ? 'selected' : '' ?>>Désactivés</option>
            </select>
        </div>

        <div class="col-md-1 d-flex align-items-end">
            <button class="btn btn-danger w-100">Filtrer</button>
        </div>
    </form>
</div>

<div class="app-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>N°</th>
                    <th>Nom</th>
                    <th>Code Massar</th>
                    <th>Classe</th>
                    <th>Connexion</th>
                    <th>Compte</th>
                    <th>Sessions</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $student): ?>
                    <tr>
                        <td><?= e((string) $student['numero']) ?></td>
                        <td>
                            <a href="<?= e(base_url('admin/students/' . $student['id'])) ?>" class="text-decoration-none fw-semibold">
                                <?= e($student['display_name']) ?>
                            </a>
                        </td>
                        <td><?= e($student['code_massar']) ?></td>
                        <td><?= e($student['class_name']) ?></td>
                        <td>
                            <span class="badge <?= $student['can_login'] ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                <?= $student['can_login'] ? 'Autorisé' : 'Bloqué' ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?= $student['is_active'] ? 'text-bg-primary' : 'text-bg-dark' ?>">
                                <?= $student['is_active'] ? 'Actif' : 'Désactivé' ?>
                            </span>
                        </td>
                        <td><?= e((string) $student['active_sessions']) ?></td>
                        <td class="text-end">
                            <form method="POST" action="<?= e(base_url('admin/students/toggle-can-login')) ?>" class="d-inline">
                                <?= \App\Core\Csrf::input('admin.student.toggle') ?>
                                <input type="hidden" name="user_id" value="<?= e((string) $student['id']) ?>">
                                <input type="hidden" name="value" value="<?= $student['can_login'] ? '0' : '1' ?>">
                                <button class="btn btn-sm btn-outline-warning">
                                    <?= $student['can_login'] ? 'Bloquer' : 'Autoriser' ?>
                                </button>
                            </form>

                            <form method="POST" action="<?= e(base_url('admin/students/force-logout')) ?>" class="d-inline">
                                <?= \App\Core\Csrf::input('admin.student.logout') ?>
                                <input type="hidden" name="user_id" value="<?= e((string) $student['id']) ?>">
                                <button class="btn btn-sm btn-outline-danger">Déconnecter</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-secondary py-4">Aucun élève trouvé.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3 text-secondary small">
    Page <?= e((string) ($meta['page'] ?? 1)) ?> / <?= e((string) ($meta['last_page'] ?? 1)) ?> — Total : <?= e((string) ($meta['total'] ?? 0)) ?>
</div>