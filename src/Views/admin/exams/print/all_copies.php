<button onclick="window.print()" class="btn btn-primary mb-3">Imprimer</button>

<?php foreach ($copies as $copy): ?>

<div class="page">

<h3><?= htmlspecialchars($copy['student']['nom']) ?> <?= htmlspecialchars($copy['student']['prenom']) ?></h3>

<p>Classe: <?= htmlspecialchars($copy['student']['class_name']) ?></p>
<p>Note: <?= $copy['student']['score'] ?></p>

<hr>

<?php foreach ($copy['questions'] as $q): ?>
    <div>
        <strong><?= htmlspecialchars($q['question_text']) ?></strong>

        <ul>
        <?php foreach ($q['answers'] as $a): ?>
            <li>
                <?= htmlspecialchars($a['text']) ?>
            </li>
        <?php endforeach; ?>
        </ul>
    </div>
<?php endforeach; ?>

</div>

<?php endforeach; ?>