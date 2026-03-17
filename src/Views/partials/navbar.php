<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\SessionManager;

$baseUrl = rtrim((string) Config::get('app.base_url', ''), '/');

$appName = (string) Config::get('app.name', 'ExamAppV3');
$loggedIn = SessionManager::check();
$isAdmin = SessionManager::isAdmin();
$isStudent = SessionManager::isStudent();
$auth = SessionManager::auth();
$displayName = is_array($auth) ? (string) ($auth['display_name'] ?? '') : '';
?>
<header class="border-bottom bg-white sticky-top shadow-sm">
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2 fw-bold" href="<?= e(base_url()) ?>">
                <img src="<?= e(asset_url('img/logo.svg')) ?>" alt="Logo" width="38" height="38">
                <span><?= e($appName) ?></span>
            </a>

            <button
                class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#mainNavbar"
                aria-controls="mainNavbar"
                aria-expanded="false"
                aria-label="Afficher la navigation"
            >
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNavbar">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= e(base_url()) ?>">
                            <i class="bi bi-house-door me-1"></i>Accueil
                        </a>
                    </li>

                    <?php if ($isAdmin): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= e(base_url('admin/dashboard')) ?>">
                                <i class="bi bi-speedometer2 me-1"></i>Dashboard
                            </a>
                        </li>

                        <li class="nav-item dropdown">
                            <a
                                class="nav-link dropdown-toggle"
                                href="#"
                                id="adminNavbarDropdown"
                                role="button"
                                data-bs-toggle="dropdown"
                                aria-expanded="false"
                            >
                                <i class="bi bi-gear-wide-connected me-1"></i>Administration
                            </a>
                            <ul class="dropdown-menu shadow border-0 rounded-4" aria-labelledby="adminNavbarDropdown">
                                <li>
                                    <a class="dropdown-item" href="<?= e(base_url('admin/computers')) ?>">
                                        <i class="bi bi-pc-display me-2"></i>Postes
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?= e(base_url('admin/students')) ?>">
                                        <i class="bi bi-people me-2"></i>Élèves
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?= e(base_url('admin/classes')) ?>">
                                        <i class="bi bi-diagram-3 me-2"></i>Classes
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?= e(base_url('admin/exams')) ?>">
                                        <i class="bi bi-journal-check me-2"></i>Examens
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?= e(base_url('admin/monitoring')) ?>">
                                        <i class="bi bi-broadcast-pin me-2"></i>Supervision
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php endif; ?>

                    <?php if ($isStudent): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= e(base_url('student/dashboard')) ?>">
                                <i class="bi bi-person-badge me-1"></i>Espace élève
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>

                <div class="d-flex align-items-center gap-2">
                    <?php if ($loggedIn): ?>
                        <span class="badge rounded-pill text-bg-light border px-3 py-2">
                            <i class="bi bi-person-circle me-1"></i>
                            <?= e($displayName !== '' ? $displayName : 'Utilisateur connecté') ?>
                        </span>

                        <form method="POST" action="<?= e(base_url('logout')) ?>" class="d-inline">
                            <?= \App\Core\Csrf::input('auth.logout') ?>
                            <button type="submit" class="btn btn-danger btn-sm">
                                <i class="bi bi-box-arrow-right me-1"></i>Déconnexion
                            </button>
                        </form>
                    <?php else: ?>
                        <a href="<?= e(base_url('login')) ?>" class="btn btn-primary btn-sm">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Connexion
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
</header>