<?php
$exams = $exams ?? [];
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Gestion des examens</h1>
        <p class="text-muted mb-0">Activation, impression, consultation et export des notes.</p>
    </div>

    <div class="btn-group btn-group-sm" role="group">
        <a
            href="<?= e(base_url('admin/exams/export-semester?semester=s1')) ?>"
            class="btn btn-outline-success"
            data-bs-toggle="tooltip"
            title="Exporter les notes S1 (examens 1 à 6)"
            aria-label="Exporter les notes S1"
        >
            <i class="bi bi-filetype-csv me-1"></i>S1
        </a>

        <a
            href="<?= e(base_url('admin/exams/export-semester?semester=s2')) ?>"
            class="btn btn-outline-primary"
            data-bs-toggle="tooltip"
            title="Exporter les notes S2 (examens 7 à 12)"
            aria-label="Exporter les notes S2"
        >
            <i class="bi bi-filetype-csv me-1"></i>S2
        </a>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 110px;">Code</th>
                        <th>Titre</th>
                        <th style="width: 90px;">Durée</th>
                        <th style="width: 90px;">Actif</th>
                        <th style="width: 110px;">Impression</th>
                        <th style="width: 90px;">Questions</th>
                        <th style="width: 110px;">Participants</th>
                        <th class="text-end" style="width: 150px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($exams === []): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">Aucun examen.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($exams as $exam): ?>
                            <?php
                            $examId = (int) ($exam['id'] ?? 0);
                            $isActive = !empty($exam['is_active']);
                            $allowPrint = !empty($exam['allow_print']);
                            ?>
                            <tr>
                                <td class="fw-semibold"><?= e((string) ($exam['code'] ?? '')) ?></td>

                                <td>
                                    <?= e((string) ($exam['title'] ?? '')) ?>
                                </td>

                                <td><?= (int) ($exam['duration_minutes'] ?? 0) ?> min</td>

                                <td>
                                    <?php if ($isActive): ?>
                                        <span class="badge text-bg-success">Oui</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary">Non</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if ($allowPrint): ?>
                                        <span class="badge text-bg-success">Oui</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary">Non</span>
                                    <?php endif; ?>
                                </td>

                                <td><?= (int) ($exam['questions_count'] ?? 0) ?></td>
                                <td><?= (int) ($exam['participants_count'] ?? 0) ?></td>

                                <td class="text-end">
                                    <div class="btn-group btn-group-sm d-inline-flex" role="group">
                                        <a
                                            href="<?= e(base_url('admin/exams/' . $examId)) ?>"
                                            class="btn btn-outline-secondary"
                                            data-bs-toggle="tooltip"
                                            title="Voir"
                                            aria-label="Voir"
                                        >
                                            <i class="bi bi-eye"></i>
                                        </a>

                                        <form method="POST" action="<?= e(base_url('admin/exams/toggle-active')) ?>" class="d-inline">
                                            <?= \App\Core\Csrf::input('admin.exam.toggle') ?>
                                            <input type="hidden" name="exam_id" value="<?= $examId ?>">
                                            <input type="hidden" name="value" value="<?= $isActive ? '0' : '1' ?>">
                                            <button
                                                type="submit"
                                                class="btn <?= $isActive ? 'btn-outline-warning' : 'btn-outline-success' ?>"
                                                data-bs-toggle="tooltip"
                                                title="<?= $isActive ? 'Désactiver' : 'Activer' ?>"
                                                aria-label="<?= $isActive ? 'Désactiver' : 'Activer' ?>"
                                            >
                                                <i class="bi <?= $isActive ? 'bi-toggle-off' : 'bi-toggle-on' ?>"></i>
                                            </button>
                                        </form>

                                        <form method="POST" action="<?= e(base_url('admin/exams/toggle-print')) ?>" class="d-inline">
                                            <?= \App\Core\Csrf::input('admin.exam.toggle') ?>
                                            <input type="hidden" name="exam_id" value="<?= $examId ?>">
                                            <input type="hidden" name="value" value="<?= $allowPrint ? '0' : '1' ?>">
                                            <button
                                                type="submit"
                                                class="btn <?= $allowPrint ? 'btn-outline-danger' : 'btn-outline-info' ?>"
                                                data-bs-toggle="tooltip"
                                                title="<?= $allowPrint ? 'Bloquer impression' : 'Autoriser impression' ?>"
                                                aria-label="<?= $allowPrint ? 'Bloquer impression' : 'Autoriser impression' ?>"
                                            >
                                                <i class="bi <?= $allowPrint ? 'bi-printer-fill' : 'bi-printer' ?>"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>