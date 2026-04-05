<button onclick="window.print()" class="btn btn-primary mb-3">Imprimer</button>

<style>
.page { page-break-after: always; }
.correct { color: green; }
.wrong { color: red; }
</style>

<div class="page">

<h3><?= htmlspecialchars($student['nom']) ?> <?= htmlspecialchars($student['prenom']) ?></h3>
<p>Classe: <?= htmlspecialchars($student['class_name']) ?></p>
<p>Note: <?= $student['score'] ?></p>

<hr>

<?php foreach ($questions as $q): ?>
    <div>
        <strong><?= htmlspecialchars($q['question_text']) ?></strong>

        <ul>
        <?php foreach ($q['answers'] as $a): ?>
            <li class="
                <?= $a['is_correct'] ? 'correct' : '' ?>
                <?= $a['is_selected'] && !$a['is_correct'] ? 'wrong' : '' ?>
            ">
                <?= htmlspecialchars($a['text']) ?>
            </li>
        <?php endforeach; ?>
        </ul>
    </div>
<?php endforeach; ?>

</div>