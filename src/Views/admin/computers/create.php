<h1 class="h3 mb-4">Ajouter un poste</h1>

<div class="app-card">
    <form method="POST" action="<?= e(base_url('admin/computers/store')) ?>" class="row g-3">
        <?= \App\Core\Csrf::input('admin.computer.create') ?>

        <div class="col-md-6">
            <label class="form-label">Nom</label>
            <input type="text" name="name" class="form-control" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Hostname</label>
            <input type="text" name="hostname" class="form-control" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">IP LAN</label>
            <input type="text" name="ip_lan" class="form-control">
        </div>

        <div class="col-md-6">
            <label class="form-label">IP Wi-Fi</label>
            <input type="text" name="ip_wifi" class="form-control">
        </div>

        <div class="col-md-6">
            <label class="form-label">Salle</label>
            <input type="text" name="room_name" class="form-control">
        </div>

        <div class="col-md-6">
            <label class="form-label">Actif</label>
            <select name="is_active" class="form-select">
                <option value="1">Oui</option>
                <option value="0">Non</option>
            </select>
        </div>

        <div class="col-12">
            <label class="form-label">Description</label>
            <textarea name="description" rows="3" class="form-control"></textarea>
        </div>

        <div class="col-12 d-flex gap-2">
            <button class="btn btn-danger">Enregistrer</button>
            <a href="<?= e(base_url('admin/computers')) ?>" class="btn btn-outline-secondary">Annuler</a>
        </div>
    </form>
</div>