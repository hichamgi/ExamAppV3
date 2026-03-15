<?php

declare(strict_types=1);

use App\Core\Config;

$baseUrl = rtrim((string) Config::get('app.base_url', ''), '/');
$old = $old ?? [];
$error = $error ?? '';
$classes = $classes ?? [];
$role = isset($old['role']) && $old['role'] !== '' ? (string) $old['role'] : 'student';
?>

<div class="row justify-content-center">
    <div class="col-xl-10">
        <div class="row g-4 align-items-stretch">
            <div class="col-lg-5">
                <div class="hero-card h-100 p-4 p-lg-5">
                    <div class="mb-4">
                        <img src="<?= e(asset_url('img/logo.svg')) ?>" alt="Logo" width="84" height="84">
                    </div>

                    <h1 class="h2 fw-bold mb-3">ExamApp V3</h1>
                    <p class="text-secondary mb-4">
                        Bienvenue. Avant de vous connecter, veuillez lire attentivement les consignes ci-dessous.
                    </p>

                    <div class="d-grid gap-3">
                        <div class="mini-info">
                            <i class="bi bi-person-vcard"></i>
                            <div>
                                <strong>Identifiants nécessaires</strong>
                                <div class="small text-secondary">
                                    Munissez-vous de votre <strong>Code Massar</strong> et du <strong>mot de passe fourni par le professeur d’informatique</strong>.
                                    Ce mot de passe est utilisé uniquement pour cette application et <strong>n’a aucun lien avec votre mot de passe Massar habituel</strong>.
                                </div>
                            </div>
                        </div>

                        <div class="mini-info">
                            <i class="bi bi-diagram-3"></i>
                            <div>
                                <strong>Choix obligatoire de la classe</strong>
                                <div class="small text-secondary">
                                    Vous devez <strong>sélectionner correctement votre classe</strong> avant de vous connecter.
                                    Sans cela, l’accès à l’examen ne sera pas autorisé.
                                </div>
                            </div>
                        </div>

                        <div class="mini-info">
                            <i class="bi bi-person-check"></i>
                            <div>
                                <strong>Vérification de votre identité</strong>
                                <div class="small text-secondary">
                                    Après la connexion, vérifiez soigneusement que les informations affichées sont bien les vôtres,
                                    en particulier <strong>votre nom, votre numéro et votre classe</strong>.
                                    Ne commencez pas l’examen tant que vous n’avez pas confirmé que ces informations sont exactes.
                                </div>
                            </div>
                        </div>

                        <div class="mini-info">
                            <i class="bi bi-shield-exclamation"></i>
                            <div>
                                <strong>Règles importantes</strong>
                                <div class="small text-secondary">
                                    Toute tentative de fraude, d’usurpation d’identité, de connexion avec les identifiants d’un autre élève
                                    ou de non-respect des consignes pourra entraîner des sanctions, y compris <strong>la note zéro</strong>.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="app-card shadow-soft">
                    <div class="card-body p-4 p-lg-5">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h2 class="h3 mb-1">Se connecter</h2>
                                <p class="small text-secondary mb-4">
                                    Renseignez vos informations exactement comme fournies par votre professeur.
                                </p>
                            </div>
                        </div>

                        <?php if ($error !== ''): ?>
                            <div class="alert alert-danger d-flex align-items-start gap-2">
                                <i class="bi bi-exclamation-triangle-fill mt-1"></i>
                                <div><?= e($error) ?></div>
                            </div>
                        <?php endif; ?>

                        <ul class="nav nav-pills nav-fill mb-4" id="loginTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button
                                    class="nav-link <?= $role === 'student' ? 'active' : '' ?>"
                                    id="student-tab"
                                    data-bs-toggle="pill"
                                    data-bs-target="#student-pane"
                                    type="button"
                                    role="tab"
                                >
                                    <i class="bi bi-mortarboard me-2"></i>Élève
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button
                                    class="nav-link <?= $role === 'admin' ? 'active' : '' ?>"
                                    id="admin-tab"
                                    data-bs-toggle="pill"
                                    data-bs-target="#admin-pane"
                                    type="button"
                                    role="tab"
                                >
                                    <i class="bi bi-person-gear me-2"></i>Admin
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content">
                            <div
                                class="tab-pane fade <?= $role === 'student' ? 'show active' : '' ?>"
                                id="student-pane"
                                role="tabpanel"
                            >
                                <form method="POST" action="<?= e($baseUrl . '/login') ?>" class="row g-3">
                                    <?= \App\Core\Csrf::input('auth.login') ?>
                                    <input type="hidden" name="role" value="student">

                                    <div class="col-md-6">
                                        <label for="code_massar" class="form-label">Code Massar</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-person-vcard"></i></span>
                                            <input
                                                type="text"
                                                class="form-control"
                                                id="code_massar"
                                                name="code_massar"
                                                value="<?= e((string) ($old['code_massar'] ?? '')) ?>"
                                                required
                                            >
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="class_id" class="form-label">Classe</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-diagram-3"></i></span>
                                            <select class="form-select" id="class_id" name="class_id" required>
                                                <option value="">Choisir...</option>
                                                <?php foreach ($classes as $class): ?>
                                                    <option
                                                        value="<?= e((string) $class['id']) ?>"
                                                        <?= ((int) ($old['class_id'] ?? 0) === (int) $class['id']) ? 'selected' : '' ?>
                                                    >
                                                        <?= e((string) $class['label']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <label for="student_password" class="form-label">Mot de passe</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                            <input
                                                type="password"
                                                class="form-control"
                                                id="student_password"
                                                name="password"
                                                required
                                            >
                                            <button type="button" class="btn btn-outline-secondary js-toggle-password" data-target="student_password">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="col-12 d-grid">
                                        <button type="submit" class="btn btn-danger btn-lg">
                                            <i class="bi bi-box-arrow-in-right me-2"></i>Connexion élève
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <div
                                class="tab-pane fade <?= $role === 'admin' ? 'show active' : '' ?>"
                                id="admin-pane"
                                role="tabpanel"
                            >
                                <form method="POST" action="<?= e($baseUrl . '/login') ?>" class="row g-3">
                                    <?= \App\Core\Csrf::input('auth.login') ?>
                                    <input type="hidden" name="role" value="admin">

                                    <div class="col-12">
                                        <label for="identifier" class="form-label">Identifiant admin</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-person-gear"></i></span>
                                            <input
                                                type="text"
                                                class="form-control"
                                                id="identifier"
                                                name="identifier"
                                                value="<?= e((string) ($old['identifier'] ?? '')) ?>"
                                                required
                                            >
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <label for="admin_password" class="form-label">Mot de passe</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-shield-lock"></i></span>
                                            <input
                                                type="password"
                                                class="form-control"
                                                id="admin_password"
                                                name="password"
                                                required
                                            >
                                            <button type="button" class="btn btn-outline-secondary js-toggle-password" data-target="admin_password">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="col-12 d-grid">
                                        <button type="submit" class="btn btn-dark btn-lg">
                                            <i class="bi bi-person-check me-2"></i>Connexion admin
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
