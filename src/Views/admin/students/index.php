<?php
$items = $students['items'] ?? [];
$meta = $students['meta'] ?? [
    'page' => 1,
    'per_page' => 25,
    'total' => 0,
    'last_page' => 1,
];

$currentPage = max(1, (int) ($meta['page'] ?? 1));
$lastPage = max(1, (int) ($meta['last_page'] ?? 1));
$total = max(0, (int) ($meta['total'] ?? 0));

$filters = $filters ?? [
    'search' => '',
    'class_id' => '',
    'can_login' => '',
    'is_active' => '',
];

$queryBase = [
    'search' => (string) ($filters['search'] ?? ''),
    'class_id' => ($filters['class_id'] ?? '') === null ? '' : (string) ($filters['class_id'] ?? ''),
    'can_login' => ($filters['can_login'] ?? '') === null ? '' : (string) ($filters['can_login'] ?? ''),
    'is_active' => ($filters['is_active'] ?? '') === null ? '' : (string) ($filters['is_active'] ?? ''),
];

$buildPageUrl = static function (int $page) use ($queryBase): string {
    $query = $queryBase;
    $query['page'] = max(1, $page);
    return base_url('admin/students?' . http_build_query($query));
};
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-1">Gestion des élèves</h1>
        <p class="text-muted mb-0">Recherche, activation, autorisation de connexion et déconnexion forcée.</p>
    </div>
</div>

<div class="card shadow-sm border-0 mb-3">
    <div class="card-body">
        <form method="GET" action="<?= e(base_url('admin/students')) ?>" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Recherche</label>
                <input
                    type="text"
                    name="search"
                    class="form-control"
                    value="<?= e((string) ($filters['search'] ?? '')) ?>"
                    placeholder="Nom, prénom, Massar..."
                >
            </div>

            <div class="col-md-3">
                <label class="form-label">Classe</label>
                <select name="class_id" class="form-select">
                    <option value="">Toutes</option>
                    <?php foreach (($classes ?? []) as $class): ?>
                        <option
                            value="<?= (int) $class['id'] ?>"
                            <?= ((string) ($filters['class_id'] ?? '') === (string) $class['id']) ? 'selected' : '' ?>
                        >
                            <?= e(($class['name'] ?? '') . (!empty($class['school_year']) ? ' — ' . $class['school_year'] : '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label">Connexion</label>
                <select name="can_login" class="form-select">
                    <option value="">Tous</option>
                    <option value="1" <?= ((string) ($filters['can_login'] ?? '') === '1') ? 'selected' : '' ?>>Autorisés</option>
                    <option value="0" <?= ((string) ($filters['can_login'] ?? '') === '0') ? 'selected' : '' ?>>Bloqués</option>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label">Statut</label>
                <select name="is_active" class="form-select">
                    <option value="">Tous</option>
                    <option value="1" <?= ((string) ($filters['is_active'] ?? '') === '1') ? 'selected' : '' ?>>Actifs</option>
                    <option value="0" <?= ((string) ($filters['is_active'] ?? '') === '0') ? 'selected' : '' ?>>Désactivés</option>
                </select>
            </div>

            <div class="col-md-1 d-grid">
                <label class="form-label">&nbsp;</label>
                <button class="btn btn-primary" type="submit">
                    <i class="bi bi-funnel"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<div class="app-card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 70px;">N°</th>
                        <th>Nom</th>
                        <th style="width: 150px;">Code Massar</th>
                        <th style="width: 180px;">Classe</th>
                        <th style="width: 110px;">Connexion</th>
                        <th style="width: 110px;">Compte</th>
                        <th style="width: 90px;">Sessions</th>
                        <th class="text-end" style="width: 150px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">Aucun élève trouvé.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($items as $student): ?>
                            <?php
                            $studentId = (int) ($student['id'] ?? 0);
                            $isActive = !empty($student['is_active']);
                            $canLogin = !empty($student['can_login']);
                            $activeSessions = (int) ($student['active_sessions'] ?? 0);
                            $isArchived = !empty($student['is_archived_candidate']);
                            $numero = (int) ($student['numero'] ?? 0);
                            $classLabel = trim((string) ($student['class_name'] ?? ''));
                            if (!empty($student['school_year'])) {
                                $classLabel .= ($classLabel !== '' ? ' — ' : '') . $student['school_year'];
                            }
                            $arabicName = trim(((string) ($student['nom_ar'] ?? '')) . ' ' . ((string) ($student['prenom_ar'] ?? '')));
                            ?>
                            <tr class="<?= $isArchived ? 'table-secondary' : '' ?>">
                                <td>
                                    <?php if ($isArchived): ?>
                                        <span class="badge text-bg-secondary" title="Élève archivé">Archivé</span>
                                    <?php else: ?>
                                        <span class="fw-semibold"><?= $numero ?></span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <div class="fw-semibold text-truncate" style="max-width: 320px;" title="<?= e((string) ($student['display_name'] ?? '')) ?>">
                                        <?= e((string) ($student['display_name'] ?? '')) ?>
                                    </div>
                                    <?php if ($arabicName !== ''): ?>
                                        <div class="small text-muted text-truncate" style="max-width: 320px;" title="<?= e($arabicName) ?>">
                                            <?= e($arabicName) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <span class="text-monospace"><?= e((string) ($student['code_massar'] ?? '')) ?></span>
                                </td>

                                <td>
                                    <?php if ($classLabel !== ''): ?>
                                        <span class="text-truncate d-inline-block" style="max-width: 160px;" title="<?= e($classLabel) ?>">
                                            <?= e($classLabel) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if ($canLogin): ?>
                                        <span class="badge text-bg-success">Oui</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-warning">Non</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if ($isActive): ?>
                                        <span class="badge text-bg-success">Actif</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-danger">Off</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if ($activeSessions > 0): ?>
                                        <span class="badge text-bg-primary"><?= $activeSessions ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">0</span>
                                    <?php endif; ?>
                                </td>

                                <td class="text-end">
                                    <div class="btn-group btn-group-sm d-inline-flex" role="group">
                                        <a
                                            href="<?= e(base_url('admin/students/' . $studentId)) ?>"
                                            class="btn btn-outline-secondary"
                                            data-bs-toggle="tooltip"
                                            title="Voir"
                                            aria-label="Voir"
                                        >
                                            <i class="bi bi-eye"></i>
                                        </a>

                                        <button
                                            type="button"
                                            class="btn <?= $isActive ? 'btn-outline-warning' : 'btn-outline-success' ?> js-student-action"
                                            data-action="<?= e(base_url('admin/students/toggle-active')) ?>"
                                            data-token="<?= e($csrf_student_toggle ?? '') ?>"
                                            data-user-id="<?= $studentId ?>"
                                            data-value="<?= $isActive ? '0' : '1' ?>"
                                            data-confirm="<?= $isActive ? 'Désactiver cet élève ?' : 'Activer cet élève ?' ?>"
                                            data-bs-toggle="tooltip"
                                            title="<?= $isActive ? 'Désactiver' : 'Activer' ?>"
                                            aria-label="<?= $isActive ? 'Désactiver' : 'Activer' ?>"
                                        >
                                            <i class="bi <?= $isActive ? 'bi-person-x' : 'bi-person-check' ?>"></i>
                                        </button>

                                        <button
                                            type="button"
                                            class="btn btn-outline-info js-student-action"
                                            data-action="<?= e(base_url('admin/students/toggle-login')) ?>"
                                            data-token="<?= e($csrf_student_toggle ?? '') ?>"
                                            data-user-id="<?= $studentId ?>"
                                            data-value="<?= $canLogin ? '0' : '1' ?>"
                                            data-confirm="<?= $canLogin ? 'Bloquer la connexion de cet élève ?' : 'Autoriser la connexion de cet élève ?' ?>"
                                            data-bs-toggle="tooltip"
                                            title="<?= $canLogin ? 'Bloquer connexion' : 'Autoriser connexion' ?>"
                                            aria-label="<?= $canLogin ? 'Bloquer connexion' : 'Autoriser connexion' ?>"
                                        >
                                            <i class="bi <?= $canLogin ? 'bi-lock' : 'bi-unlock' ?>"></i>
                                        </button>

                                        <button
                                            type="button"
                                            class="btn btn-outline-danger js-student-action"
                                            data-action="<?= e(base_url('admin/students/force-logout')) ?>"
                                            data-token="<?= e($csrf_student_logout ?? '') ?>"
                                            data-user-id="<?= $studentId ?>"
                                            data-confirm="Déconnecter immédiatement cet élève ?"
                                            data-bs-toggle="tooltip"
                                            title="Déconnecter"
                                            aria-label="Déconnecter"
                                        >
                                            <i class="bi bi-box-arrow-right"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="text-muted">
            Page <?= $currentPage ?> / <?= $lastPage ?> — Total : <?= $total ?>
        </div>

        <?php if ($lastPage > 1): ?>
            <nav aria-label="Pagination des élèves">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $currentPage <= 1 ? '#' : e($buildPageUrl(1)) ?>">«</a>
                    </li>

                    <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $currentPage <= 1 ? '#' : e($buildPageUrl($currentPage - 1)) ?>">‹</a>
                    </li>

                    <?php
                    $start = max(1, $currentPage - 2);
                    $end = min($lastPage, $currentPage + 2);
                    ?>

                    <?php for ($i = $start; $i <= $end; $i++): ?>
                        <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
                            <a class="page-link" href="<?= e($buildPageUrl($i)) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>

                    <li class="page-item <?= $currentPage >= $lastPage ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $currentPage >= $lastPage ? '#' : e($buildPageUrl($currentPage + 1)) ?>">›</a>
                    </li>

                    <li class="page-item <?= $currentPage >= $lastPage ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $currentPage >= $lastPage ? '#' : e($buildPageUrl($lastPage)) ?>">»</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<form id="student-action-form" method="POST" action="" class="d-none">
    <input type="hidden" name="_token" value="">
    <input type="hidden" name="user_id" value="">
    <input type="hidden" name="value" value="">
</form>

<style>
    .table td,
    .table th {
        padding-top: .45rem;
        padding-bottom: .45rem;
        white-space: nowrap;
    }

    .table td .btn-group .btn {
        padding: .25rem .45rem;
        line-height: 1.1;
    }

    .text-monospace {
        font-family: monospace;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const actionForm = document.getElementById('student-action-form');

    if (!actionForm) {
        return;
    }

    const tokenInput = actionForm.querySelector('input[name="_token"]');
    const userIdInput = actionForm.querySelector('input[name="user_id"]');
    const valueInput = actionForm.querySelector('input[name="value"]');

    document.addEventListener('click', function (event) {
        const button = event.target.closest('.js-student-action');

        if (!button) {
            return;
        }

        const action = button.getAttribute('data-action') || '';
        const token = button.getAttribute('data-token') || '';
        const userId = button.getAttribute('data-user-id') || '';
        const value = button.getAttribute('data-value');
        const confirmMessage = button.getAttribute('data-confirm') || '';

        if (confirmMessage !== '' && !window.confirm(confirmMessage)) {
            return;
        }

        actionForm.setAttribute('action', action);
        tokenInput.value = token;
        userIdInput.value = userId;

        if (value !== null) {
            valueInput.value = value;
            valueInput.disabled = false;
        } else {
            valueInput.value = '';
            valueInput.disabled = true;
        }

        actionForm.submit();
    });
});
</script>