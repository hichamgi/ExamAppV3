<h1 class="h3 mb-4">Détail examen</h1>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="app-card">
            <h2 class="h5 mb-3"><?= e($exam['title']) ?></h2>
            <p class="mb-1"><strong>Code :</strong> <?= e($exam['code']) ?></p>
            <p class="mb-1"><strong>Durée :</strong> <?= e((string) $exam['duration_minutes']) ?> min</p>
            <p class="mb-1"><strong>Actif :</strong> <?= $exam['is_active'] ? 'Oui' : 'Non' ?></p>
            <p class="mb-3"><strong>Impression :</strong> <?= $exam['allow_print'] ? 'Oui' : 'Non' ?></p>

            <form method="GET" action="<?= e(base_url('admin/exams/' . $exam['id'])) ?>" class="mb-3">
                <label class="form-label">Filtrer par classe</label>
                <select name="class_id" class="form-select mb-2">
                    <option value="">Toutes</option>
                    <?php foreach (($classes ?? []) as $class): ?>
                        <option value="<?= e((string) $class['id']) ?>" <?= ((int) ($selected_class_id ?? 0) === (int) $class['id']) ? 'selected' : '' ?>>
                            <?= e($class['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-danger w-100">Filtrer</button>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="app-card">
            <h2 class="h5 mb-3">Résultats</h2>

            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>N°</th>
                            <th>Élève</th>
                            <th>Classe</th>
                            <th>Statut</th>
                            <th>Score</th>
                            <th>Répondues</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($results ?? []) as $result): ?>
                            <tr>
                                <td><?= e((string) $result['numero']) ?></td>
                                <td><?= e($result['student_name']) ?></td>
                                <td><?= e($result['class_name']) ?></td>
                                <td><?= e($result['status']) ?></td>
                                <td><?= e((string) $result['final_score']) ?></td>
                                <td><?= e((string) $result['answered_questions']) ?>/<?= e((string) $result['total_questions']) ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($results)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-secondary py-3">Aucun résultat.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>