<h1 class="h3 mb-4">Gestion des classes</h1>

<div class="app-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Classe</th>
                    <th>Année</th>
                    <th>Actif</th>
                    <th>Élèves</th>
                    <th>Autorisés</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (($classes ?? []) as $class): ?>
                    <tr>
                        <td>
                            <a href="<?= e(base_url('admin/classes/' . $class['id'])) ?>" class="text-decoration-none fw-semibold">
                                <?= e($class['name']) ?>
                            </a>
                        </td>
                        <td><?= e($class['school_year']) ?></td>
                        <td><?= $class['is_active'] ? 'Oui' : 'Non' ?></td>
                        <td><?= e((string) $class['students_count']) ?></td>
                        <td><?= e((string) $class['can_login_count']) ?></td>
                        <td class="text-end">
                            <a
                                href="<?= e(base_url('admin/classes/' . (int) $class['id'])) ?>"
                                class="btn btn-sm btn-outline-primary"
                                data-bs-toggle="tooltip"
                                title="Gérer les autorisations"
                                aria-label="Gérer les autorisations"
                            >
                                <i class="bi bi-shield-lock me-1"></i> Gérer
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($classes)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-secondary py-4">Aucune classe trouvée.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>