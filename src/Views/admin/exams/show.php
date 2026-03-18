<?php
declare(strict_types=1);

/** @var array $exam */
/** @var array $results */
/** @var array $classes */
/** @var int $selected_class_id */
/** @var array $assignment_data */
/** @var string $csrf_exam_toggle */
/** @var string $csrf_exam_assignment */

$metadata = $exam['metadata_array'] ?? [];
$rows = $assignment_data['rows'] ?? [];
$totals = $assignment_data['totals'] ?? ['TCT' => 0, 'TCS' => 0, 'TCL' => 0];

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

$moduleLabel = trim((string) ($metadata['module_abrev'] ?? ''));
if ($moduleLabel !== '' && !empty($metadata['module'])) {
    $moduleLabel .= ' - ';
}
$moduleLabel .= (string) ($metadata['module'] ?? '');

$typeLabel = (string) ($metadata['type'] ?? '');
$idModule = $metadata['idmodule'] ?? ($metadata['legacy_idmodule'] ?? null);
$divisionId = $metadata['division_id'] ?? null;

$resultsCount = count($results);
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1"><?= e($exam['title'] ?? 'Examen') ?></h1>
            <div class="text-muted small">
                <span class="me-3"><strong>Code :</strong> <?= e($exam['code'] ?? '') ?></span>
                <span class="me-3"><strong>ID :</strong> <?= (int) ($exam['id'] ?? 0) ?></span>
                <span class="me-3"><strong>Durée :</strong> <?= (int) ($exam['duration_minutes'] ?? 0) ?> min</span>
                <span class="me-3"><strong>Questions :</strong> <?= (int) ($exam['questions_count'] ?? 0) ?></span>
                <span class="me-3"><strong>Participants :</strong> <?= (int) ($exam['participants_count'] ?? 0) ?></span>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <a href="<?= e(base_url('admin/exams')) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i>
                Retour
            </a>

            <form action="<?= e(base_url('admin/exams/toggle-active')) ?>" method="post" class="d-inline">
                <input type="hidden" name="_csrf" value="<?= e($csrf_exam_toggle) ?>">
                <input type="hidden" name="exam_id" value="<?= (int) ($exam['id'] ?? 0) ?>">
                <input type="hidden" name="value" value="<?= !empty($exam['is_active']) ? '0' : '1' ?>">
                <button type="submit" class="btn btn-sm <?= !empty($exam['is_active']) ? 'btn-outline-danger' : 'btn-outline-success' ?>">
                    <i class="bi <?= !empty($exam['is_active']) ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i>
                    <?= !empty($exam['is_active']) ? 'Désactiver' : 'Activer' ?>
                </button>
            </form>

            <form action="<?= e(base_url('admin/exams/toggle-print')) ?>" method="post" class="d-inline">
                <input type="hidden" name="_csrf" value="<?= e($csrf_exam_toggle) ?>">
                <input type="hidden" name="exam_id" value="<?= (int) ($exam['id'] ?? 0) ?>">
                <input type="hidden" name="value" value="<?= !empty($exam['allow_print']) ? '0' : '1' ?>">
                <button type="submit" class="btn btn-sm <?= !empty($exam['allow_print']) ? 'btn-outline-warning' : 'btn-outline-primary' ?>">
                    <i class="bi <?= !empty($exam['allow_print']) ? 'bi-printer-fill' : 'bi-printer' ?>"></i>
                    <?= !empty($exam['allow_print']) ? 'Interdire impression' : 'Autoriser impression' ?>
                </button>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white">
                    <h2 class="h6 mb-0">Informations examen</h2>
                </div>
                <div class="card-body">
                    <div class="row g-3 small">
                        <div class="col-md-6">
                            <div><strong>Module :</strong> <?= e($moduleLabel) ?></div>
                            <div><strong>Type :</strong> <?= e($typeLabel) ?></div>
                            <div><strong>ID module :</strong> <?= e($idModule) ?></div>
                            <div><strong>Division :</strong> <?= e($divisionId) ?></div>
                        </div>
                        <div class="col-md-6">
                            <div>
                                <strong>État :</strong>
                                <?php if (!empty($exam['is_active'])): ?>
                                    <span class="badge text-bg-success">Actif</span>
                                <?php else: ?>
                                    <span class="badge text-bg-secondary">Inactif</span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <strong>Impression :</strong>
                                <?php if (!empty($exam['allow_print'])): ?>
                                    <span class="badge text-bg-primary">Autorisée</span>
                                <?php else: ?>
                                    <span class="badge text-bg-secondary">Interdite</span>
                                <?php endif; ?>
                            </div>
                            <div><strong>Créé le :</strong> <?= e($exam['created_at'] ?? '') ?></div>
                            <div><strong>Mis à jour le :</strong> <?= e($exam['updated_at'] ?? '') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white">
                    <h2 class="h6 mb-0">Totaux affectation</h2>
                </div>
                <div class="card-body">
                    <div class="row g-2 text-center">
                        <div class="col-4">
                            <div class="border rounded p-2">
                                <div class="small text-muted">TCT</div>
                                <div class="h5 mb-0"><?= e(number_format((float) ($totals['TCT'] ?? 0), 2, '.', '')) ?></div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border rounded p-2">
                                <div class="small text-muted">TCS</div>
                                <div class="h5 mb-0"><?= e(number_format((float) ($totals['TCS'] ?? 0), 2, '.', '')) ?></div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border rounded p-2">
                                <div class="small text-muted">TCL</div>
                                <div class="h5 mb-0"><?= e(number_format((float) ($totals['TCL'] ?? 0), 2, '.', '')) ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 small text-muted">
                        Les valeurs représentent la note totale potentielle selon les quantités affectées par numéro.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h2 class="h6 mb-0">Affectation des questions par type de classe</h2>
            <span class="badge text-bg-light"><?= count($rows) ?> numéros</span>
        </div>
        <div class="card-body">
            <form action="<?= e(base_url('admin/exams/save-assignment')) ?>" method="post">
                <input type="hidden" name="_csrf" value="<?= e($csrf_exam_assignment) ?>">
                <input type="hidden" name="exam_id" value="<?= (int) ($exam['id'] ?? 0) ?>">

                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-sm align-middle mb-3">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 80px;">Num</th>
                                <th>Questions disponibles</th>
                                <th style="width: 100px;">Points</th>
                                <th style="width: 110px;">Nb dispo</th>
                                <th style="width: 110px;">TCT</th>
                                <th style="width: 110px;">TCS</th>
                                <th style="width: 110px;">TCL</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rows)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        Aucune question trouvée pour cet examen.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($rows as $row): ?>
                                    <?php
                                    $num = (int) ($row['num'] ?? 0);
                                    $availableCount = (int) ($row['available_count'] ?? 0);
                                    $questions = $row['questions'] ?? [];
                                    $assigned = $row['assigned'] ?? ['TCT' => 0, 'TCS' => 0, 'TCL' => 0];
                                    ?>
                                    <tr>
                                        <td class="fw-semibold"><?= $num ?></td>
                                        <td>
                                            <?php if (!empty($questions)): ?>
                                                <div class="small">
                                                    <?php foreach ($questions as $index => $question): ?>
                                                        <div class="<?= $index > 0 ? 'mt-2 pt-2 border-top' : '' ?>">
                                                            <div class="fw-semibold">
                                                                Q<?= (int) ($question['id'] ?? 0) ?>
                                                                <?php if (!empty($question['type'])): ?>
                                                                    <span class="text-muted">[<?= e($question['type']) ?>]</span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div><?= e($question['question_text'] ?? '') ?></div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= e(number_format((float) ($row['points'] ?? 0), 2, '.', '')) ?></td>
                                        <td><?= $availableCount ?></td>
                                        <td>
                                            <input
                                                type="number"
                                                class="form-control form-control-sm"
                                                name="assign_TCT_<?= $num ?>"
                                                min="0"
                                                max="<?= $availableCount ?>"
                                                step="1"
                                                value="<?= (int) ($assigned['TCT'] ?? 0) ?>"
                                            >
                                        </td>
                                        <td>
                                            <input
                                                type="number"
                                                class="form-control form-control-sm"
                                                name="assign_TCS_<?= $num ?>"
                                                min="0"
                                                max="<?= $availableCount ?>"
                                                step="1"
                                                value="<?= (int) ($assigned['TCS'] ?? 0) ?>"
                                            >
                                        </td>
                                        <td>
                                            <input
                                                type="number"
                                                class="form-control form-control-sm"
                                                name="assign_TCL_<?= $num ?>"
                                                min="0"
                                                max="<?= $availableCount ?>"
                                                step="1"
                                                value="<?= (int) ($assigned['TCL'] ?? 0) ?>"
                                            >
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <?php if (!empty($rows)): ?>
                            <tfoot>
                                <tr class="table-light">
                                    <th colspan="4" class="text-end">Total points</th>
                                    <th><?= e(number_format((float) ($totals['TCT'] ?? 0), 2, '.', '')) ?></th>
                                    <th><?= e(number_format((float) ($totals['TCS'] ?? 0), 2, '.', '')) ?></th>
                                    <th><?= e(number_format((float) ($totals['TCL'] ?? 0), 2, '.', '')) ?></th>
                                </tr>
                            </tfoot>
                        <?php endif; ?>
                    </table>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-save"></i>
                        Enregistrer l’affectation
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h2 class="h6 mb-0">Résultats</h2>

            <form method="get" action="<?= e(base_url('admin/exams/' . (int) ($exam['id'] ?? 0))) ?>" class="d-flex gap-2">
                <select name="class_id" class="form-select form-select-sm">
                    <option value="0">Toutes les classes</option>
                    <?php foreach ($classes as $class): ?>
                        <option
                            value="<?= (int) ($class['id'] ?? 0) ?>"
                            <?= (int) ($selected_class_id ?? 0) === (int) ($class['id'] ?? 0) ? 'selected' : '' ?>
                        >
                            <?= e($class['name'] ?? '') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-funnel"></i>
                    Filtrer
                </button>
            </form>
        </div>

        <div class="card-body">
            <?php if ($resultsCount === 0): ?>
                <div class="text-muted">Aucun résultat pour ce filtre.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Massar</th>
                                <th>Élève</th>
                                <th>Classe</th>
                                <th>Statut</th>
                                <th>Abs</th>
                                <th>Ret</th>
                                <th>Triche</th>
                                <th>Rép</th>
                                <th>Justes</th>
                                <th>Fausses</th>
                                <th>Vides</th>
                                <th>Note</th>
                                <th>Début</th>
                                <th>Fin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $index => $result): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= e($result['code_massar'] ?? '') ?></td>
                                    <td>
                                        <div class="fw-semibold"><?= e($result['student_name'] ?? '') ?></div>
                                        <div class="small text-muted">N° <?= (int) ($result['numero'] ?? 0) ?></div>
                                    </td>
                                    <td><?= e($result['class_name'] ?? '') ?></td>
                                    <td><?= e($result['status'] ?? '') ?></td>
                                    <td class="text-center"><?= !empty($result['is_absent']) ? 'A' : '' ?></td>
                                    <td class="text-center"><?= !empty($result['is_retake']) ? 'R' : '' ?></td>
                                    <td class="text-center"><?= !empty($result['is_cheat']) ? 'T' : '' ?></td>
                                    <td class="text-end"><?= (int) ($result['answered_questions'] ?? 0) ?></td>
                                    <td class="text-end"><?= (int) ($result['correct_questions'] ?? 0) ?></td>
                                    <td class="text-end"><?= (int) ($result['wrong_questions'] ?? 0) ?></td>
                                    <td class="text-end"><?= (int) ($result['blank_questions'] ?? 0) ?></td>
                                    <td class="text-end fw-semibold"><?= e(number_format((float) ($result['final_score'] ?? 0), 2, '.', '')) ?></td>
                                    <td class="small"><?= e($result['started_at'] ?? '') ?></td>
                                    <td class="small"><?= e($result['submitted_at'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>