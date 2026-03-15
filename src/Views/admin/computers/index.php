<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Gestion des postes</h1>
        <p class="text-secondary mb-0">Lister, modifier, activer ou supprimer les postes de la salle.</p>
    </div>
    <a href="<?= e(base_url('admin/computers/create')) ?>" class="btn btn-danger">
        <i class="bi bi-plus-circle me-2"></i>Nouveau poste
    </a>
</div>

<div class="app-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Hostname</th>
                    <th>LAN</th>
                    <th>Wi-Fi</th>
                    <th>Salle</th>
                    <th>Actif</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (($computers ?? []) as $computer): ?>
                    <tr>
                        <td><?= e($computer['name']) ?></td>
                        <td><?= e($computer['hostname']) ?></td>
                        <td><?= e($computer['ip_lan']) ?></td>
                        <td><?= e($computer['ip_wifi']) ?></td>
                        <td><?= e($computer['room_name']) ?></td>
                        <td>
                            <span class="badge <?= $computer['is_active'] ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                <?= $computer['is_active'] ? 'Oui' : 'Non' ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="<?= e(base_url('admin/computers/' . $computer['id'] . '/edit')) ?>" class="btn btn-sm btn-outline-dark">
                                Modifier
                            </a>
                            <form method="POST" action="<?= e(base_url('admin/computers/toggle-active')) ?>" class="d-inline">
                                <?= \App\Core\Csrf::input('admin.computer.toggle') ?>
                                <input type="hidden" name="computer_id" value="<?= e((string) $computer['id']) ?>">
                                <input type="hidden" name="value" value="<?= $computer['is_active'] ? '0' : '1' ?>">
                                <button type="submit" class="btn btn-sm <?= $computer['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?>">
                                    <?= $computer['is_active'] ? 'Désactiver' : 'Activer' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($computers)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-secondary py-4">Aucun poste trouvé.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>