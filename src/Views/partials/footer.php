<?php

declare(strict_types=1);

use App\Core\Config;

$appName = (string) Config::get('app.name', 'ExamAppV3');
?>
<footer class="border-top bg-white mt-auto">
    <div class="container py-3 d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 small text-muted">
        <div><?= e($appName) ?> — plateforme d’examen</div>
        <div class="texte-maghribi">هشام عريض</div>
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-shield-check"></i>
            <span>Mode salle informatique sécurisé</span>
        </div>
    </div>
</footer>
