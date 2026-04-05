<button onclick="window.print()" class="btn btn-primary mb-3">Imprimer</button>

<style>
@media print {
    button { display: none; }
}

.ticket {
    border: 1px solid #000;
    padding: 10px;
    margin-bottom: 10px;
}
</style>

<?php foreach ($tickets as $t): ?>
<div class="ticket">
    <strong><?= htmlspecialchars($t['nom']) ?> <?= htmlspecialchars($t['prenom']) ?></strong><br>
    Classe: <?= htmlspecialchars($t['class_name']) ?><br>
    Login: <?= htmlspecialchars($t['login']) ?><br>
    Mot de passe: <?= htmlspecialchars($t['plain_password']) ?>
</div>
<?php endforeach; ?>