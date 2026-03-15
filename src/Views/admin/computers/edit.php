<h1 class="h3 mb-4">Modifier un poste</h1>

<div class="app-card">
    <form method="POST" action="<?= e(base_url('admin/computers/' . $computer['id'] . '/update')) ?>" class="row g-3">
        <?= \App\Core\Csrf::input('admin.computer.update') ?>

        <div class="col-md-6">
            <label class="form-label">Nom</label>
            <input type="text" name="name" class="form-control" value="<?= e($computer['name']) ?>" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Hostname</label>
            <input type="text" name="hostname" class="form-control" value="<?= e($computer['hostname']) ?>" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">IP LAN</label>
            <input type="text" name="ip_lan" class="form-control" value="<?= e($computer['ip_lan']) ?>">
        </div>

        <div class="col-md-6">
            <label class="form-label">IP Wi-Fi</label>
            <input type="text" name="ip_wifi" class="form-control" value="<?= e($computer['ip_wifi']) ?>">
        </div>

        <div class="col-md-6">
            <label class="form-label">Salle</label>
            <input type="text" name="room_name" class="form-control" value="<?= e($computer['room_name']) ?>">
        </div>

        <div class="col-md-6">
            <label class="form-label">Actif</label>
            <select name="is_active" class="form-select">
                <option value="1" <?= $computer['is_active'] ? 'selected' : '' ?>>Oui</option>
                <option value="0" <?= !$computer['is_active'] ? 'selected' : '' ?>>Non</option>
            </select>
        </div>

        <div class="col-12">
            <label class="form-label">Description</label>
            <textarea name="description" rows="3" class="form-control"><?= e($computer['description']) ?></textarea>
        </div>

        <div class="col-12 d-flex gap-2">
            <button class="btn btn-danger">Enregistrer les modifications</button>
            <a href="<?= e(base_url('admin/computers')) ?>" class="btn btn-outline-secondary">Retour</a>
        </div>
    </form>
</div>