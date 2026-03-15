<?php

declare(strict_types=1);

use App\Core\Config;

$baseUrl = rtrim((string) Config::get('app.base_url', ''), '/');
?>

<section class="hero-card p-4 p-lg-5 mb-4">
    <div class="row align-items-center g-4">
        <div class="col-lg-7">
            <span class="badge rounded-pill text-bg-danger-subtle border border-danger-subtle mb-3">
                Plateforme locale d’examen
            </span>

            <h1 class="display-6 fw-bold mb-3">
                ExamApp V3
            </h1>

            <p class="lead text-secondary mb-4">
                Interface de test visuel pour valider le socle MVC, l’authentification,
                le dashboard admin et l’espace élève en environnement hors ligne.
            </p>

            <div class="d-flex flex-wrap gap-2">
                <a href="<?= e($baseUrl . '/login') ?>" class="btn btn-danger btn-lg">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Accéder à la connexion
                </a>
                <a href="<?= e($baseUrl . '/admin/dashboard') ?>" class="btn btn-outline-dark btn-lg">
                    <i class="bi bi-speedometer2 me-2"></i>Voir le dashboard admin
                </a>
                <a href="<?= e($baseUrl . '/student/dashboard') ?>" class="btn btn-outline-secondary btn-lg">
                    <i class="bi bi-person-badge me-2"></i>Voir l’espace élève
                </a>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="glass-panel p-4">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <img src="<?= e(asset_url('img/logo.svg')) ?>" alt="Logo" width="72" height="72">
                    <div>
                        <h2 class="h5 mb-1">Mode hors ligne</h2>
                        <p class="mb-0 text-secondary small">
                            Bootstrap, icônes et JS chargés localement.
                        </p>
                    </div>
                </div>

                <ul class="list-unstyled mb-0 small">
                    <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Rendu MVC stable</li>
                    <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Support admin / élève</li>
                    <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Compatible salle informatique</li>
                    <li><i class="bi bi-check-circle-fill text-success me-2"></i>Base propre pour tests visuels</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<div class="row g-4">
    <div class="col-md-4">
        <div class="app-card h-100">
            <div class="card-body">
                <div class="icon-badge mb-3"><i class="bi bi-people"></i></div>
                <h3 class="h5">Connexion par rôle</h3>
                <p class="text-secondary mb-0">
                    Admin et élève utilisent une interface claire avec logique métier séparée.
                </p>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="app-card h-100">
            <div class="card-body">
                <div class="icon-badge mb-3"><i class="bi bi-pc-display"></i></div>
                <h3 class="h5">Salle locale</h3>
                <p class="text-secondary mb-0">
                    Prévue pour fonctionner même quand Internet est totalement coupé.
                </p>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="app-card h-100">
            <div class="card-body">
                <div class="icon-badge mb-3"><i class="bi bi-shield-lock"></i></div>
                <h3 class="h5">Sécurité</h3>
                <p class="text-secondary mb-0">
                    Socle prêt pour CSRF, timeout, session réseau et journalisation.
                </p>
            </div>
        </div>
    </div>
</div>
