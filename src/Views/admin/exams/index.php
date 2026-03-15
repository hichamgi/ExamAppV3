<h1 class="h3 mb-4">Gestion des examens</h1>

<div class="app-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Titre</th>
                    <th>Durée</th>
                    <th>Actif</th>
                    <th>Impression</th>
                    <th>Questions</th>
                    <th>Participants</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (($exams ?? []) as $exam): ?>
                    <tr>
                        <td><?= e($exam['code']) ?></td>
                        <td>
                            <a href="<?= e(base_url('admin/exams/' . $exam['id'])) ?>" class="text-decoration-none fw-semibold">
                                <?= e($exam['title']) ?>
                            </a>
                        </td>
                        <td><?= e((string) $exam['duration_minutes']) ?> min</td>
                        <td><?= $exam['is_active'] ? 'Oui' : 'Non' ?></td>
                        <td><?= $exam['allow_print'] ? 'Oui' : 'Non' ?></td>
                        <td><?= e((string) $exam['questions_count']) ?></td>
                        <td><?= e((string) $exam['participants_count']) ?></td>
                        <td class="text-end">
                            <form method="POST" action="<?= e(base_url('admin/exams/toggle-active')) ?>" class="d-inline">
                                <?= \App\Core\Csrf::input('admin.exam.toggle') ?>
                                <input type="hidden" name="exam_id" value="<?= e((string) $exam['id']) ?>">
                                <input type="hidden" name="value" value="<?= $exam['is_active'] ? '0' : '1' ?>">
                                <button class="btn btn-sm btn-outline-warning">
                                    <?= $exam['is_active'] ? 'Désactiver' : 'Activer' ?>
                                </button>
                            </form>

                            <form method="POST" action="<?= e(base_url('admin/exams/toggle-print')) ?>" class="d-inline">
                                <?= \App\Core\Csrf::input('admin.exam.toggle') ?>
                                <input type="hidden" name="exam_id" value="<?= e((string) $exam['id']) ?>">
                                <input type="hidden" name="value" value="<?= $exam['allow_print'] ? '0' : '1' ?>">
                                <button class="btn btn-sm btn-outline-dark">
                                    <?= $exam['allow_print'] ? 'Retirer impression' : 'Autoriser impression' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($exams)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-secondary py-4">Aucun examen.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>