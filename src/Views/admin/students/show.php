<h1 class="h3 mb-4">Détail élève</h1>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="app-card">
            <h2 class="h5 mb-3"><?= e($student['display_name']) ?></h2>
            <p class="mb-1"><strong>Code Massar :</strong> <?= e($student['code_massar']) ?></p>
            <p class="mb-1"><strong>Numéro :</strong> <?= e((string) $student['numero']) ?></p>
            <p class="mb-1"><strong>Classe :</strong> <?= e($student['class_name']) ?></p>
            <p class="mb-1"><strong>Connexion :</strong> <?= $student['can_login'] ? 'Autorisée' : 'Bloquée' ?></p>
            <p class="mb-0"><strong>Compte :</strong> <?= $student['is_active'] ? 'Actif' : 'Désactivé' ?></p>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="app-card">
            <h2 class="h5 mb-3">Historique des examens</h2>

            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Examen</th>
                            <th>Classe</th>
                            <th>Statut</th>
                            <th>Score</th>
                            <th>Début</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($history ?? []) as $item): ?>
                            <tr>
                                <td><?= e($item['exam_title']) ?></td>
                                <td><?= e($item['class_name']) ?></td>
                                <td><?= e($item['status']) ?></td>
                                <td><?= e((string) $item['final_score']) ?></td>
                                <td><?= e($item['started_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($history)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-secondary py-3">Aucun historique.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>