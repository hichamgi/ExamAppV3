<?php

declare(strict_types=1);

$student = $student ?? [];
$network = $network ?? [];
$activeExam = $active_exam ?? null;
$questions = is_array($questions ?? null) ? $questions : [];
$computer = is_array($network['computer'] ?? null) ? $network['computer'] : null;

$renderQuestionCard = static function (array $question): void {
    $snapshot = is_array($question['snapshot'] ?? null) ? $question['snapshot'] : [];

    $type = (string) ($snapshot['type'] ?? $snapshot['t'] ?? '');
    $questionText = (string) ($snapshot['q'] ?? '');
    $options = is_array($snapshot['options'] ?? null) ? $snapshot['options'] : [];

    $questionNum = (int) ($question['question_num'] ?? 0);
    $points = (float) ($question['points'] ?? 0);
    $answerText = (string) ($question['answer_text'] ?? '');

    ?>
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                <div>
                    <div class="small text-secondary mb-1">Question <?= $questionNum ?></div>
                    <h2 class="h5 mb-0">Barème : <?= e(number_format($points, 2, '.', '')) ?> pt(s)</h2>
                </div>
            </div>

            <div class="mb-4">
                <div class="fw-semibold mb-2">Énoncé</div>
                <div class="border rounded-3 bg-light p-3" style="white-space: pre-wrap;"><?= e($questionText) ?></div>
            </div>

            <?php if ($type === 'lists'): ?>
                <div>
                    <div class="fw-semibold mb-2">Choix proposés</div>
                    <div class="list-group">
                        <?php foreach ($options as $index => $option): ?>
                            <?php
                            $optionText = is_array($option)
                                ? (string) ($option['text'] ?? '')
                                : (string) $option;

                            $inputId = 'q_' . $question['id'] . '_opt_' . $index;
                            ?>
                            <label class="list-group-item d-flex align-items-start gap-3">
                                <input
                                    class="form-check-input mt-1"
                                    type="radio"
                                    name="answers[<?= (int) $question['id'] ?>]"
                                    value="<?= e($optionText) ?>"
                                    id="<?= e($inputId) ?>"
                                    <?= $answerText === $optionText ? 'checked' : '' ?>
                                >
                                <span><?= e($optionText) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

            <?php elseif ($type === 'input'): ?>
                <div>
                    <label class="form-label fw-semibold" for="q_<?= (int) $question['id'] ?>">Réponse</label>
                    <input
                        type="text"
                        class="form-control"
                        id="q_<?= (int) $question['id'] ?>"
                        name="answers[<?= (int) $question['id'] ?>]"
                        value="<?= e($answerText) ?>"
                        autocomplete="off"
                    >
                </div>

            <?php elseif ($type === 'inputs'): ?>
                <?php
                $inputCount = max(0, (int) ($snapshot['inputs'] ?? $snapshot['input_count'] ?? 0));
                $algo = (string) ($snapshot['algo'] ?? '');
                $note = (string) ($snapshot['note'] ?? '');
                $blocks = is_array($snapshot['blocks'] ?? null) ? $snapshot['blocks'] : [];
                ?>
                <?php if ($algo !== ''): ?>
                    <div class="mb-3">
                        <div class="fw-semibold mb-2">Algorithme</div>
                        <pre class="border rounded-3 bg-light p-3 mb-0" style="white-space: pre-wrap;"><?= e($algo) ?></pre>
                    </div>
                <?php endif; ?>

                <?php if ($blocks !== []): ?>
                    <div class="mb-3">
                        <div class="fw-semibold mb-2">Blocs</div>
                        <div class="border rounded-3 bg-light p-3">
                            <?php foreach ($blocks as $block): ?>
                                <div class="mb-2"><?= e((string) $block) ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($note !== ''): ?>
                    <div class="alert alert-secondary small"><?= e($note) ?></div>
                <?php endif; ?>

                <div class="row g-3">
                    <?php
                    $existingValues = [];
                    if ($answerText !== '') {
                        $decodedAnswer = json_decode($answerText, true);
                        if (is_array($decodedAnswer)) {
                            $existingValues = $decodedAnswer;
                        }
                    }
                    ?>
                    <?php for ($i = 0; $i < $inputCount; $i++): ?>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="q_<?= (int) $question['id'] ?>_<?= $i ?>">
                                Réponse <?= $i + 1 ?>
                            </label>
                            <input
                                type="text"
                                class="form-control"
                                id="q_<?= (int) $question['id'] ?>_<?= $i ?>"
                                name="answers_multi[<?= (int) $question['id'] ?>][<?= $i ?>]"
                                value="<?= e((string) ($existingValues[$i] ?? '')) ?>"
                                autocomplete="off"
                            >
                        </div>
                    <?php endfor; ?>
                </div>

            <?php elseif ($type === 'textarea'): ?>
                <?php
                $algo = (string) ($snapshot['algo'] ?? '');
                $note = (string) ($snapshot['note'] ?? '');
                ?>
                <?php if ($algo !== ''): ?>
                    <div class="mb-3">
                        <div class="fw-semibold mb-2">Support</div>
                        <pre class="border rounded-3 bg-light p-3 mb-0" style="white-space: pre-wrap;"><?= e($algo) ?></pre>
                    </div>
                <?php endif; ?>

                <?php if ($note !== ''): ?>
                    <div class="alert alert-secondary small"><?= e($note) ?></div>
                <?php endif; ?>

                <div>
                    <label class="form-label fw-semibold" for="q_<?= (int) $question['id'] ?>">Réponse rédigée</label>
                    <textarea
                        class="form-control"
                        rows="10"
                        id="q_<?= (int) $question['id'] ?>"
                        name="answers[<?= (int) $question['id'] ?>]"
                    ><?= e($answerText) ?></textarea>
                </div>

            <?php elseif ($type === 'schema'): ?>
                <?php
                $image = (string) ($snapshot['image'] ?? '');
                ?>
                <?php if ($image !== ''): ?>
                    <div class="mb-3">
                        <div class="fw-semibold mb-2">Schéma</div>
                        <div class="border rounded-3 bg-light p-3 text-center">
                            <img
                                src="<?= e(base_url('assets/img/questions/' . $image)) ?>"
                                alt="Schéma question <?= $questionNum ?>"
                                class="img-fluid rounded"
                                style="max-height: 420px;"
                            >
                        </div>
                    </div>
                <?php endif; ?>

                <div>
                    <div class="fw-semibold mb-2">Choix proposés</div>
                    <div class="list-group">
                        <?php foreach ($options as $index => $option): ?>
                            <?php
                            $optionText = is_array($option)
                                ? (string) ($option['text'] ?? '')
                                : (string) $option;

                            $inputId = 'q_' . $question['id'] . '_schema_opt_' . $index;
                            ?>
                            <label class="list-group-item d-flex align-items-start gap-3">
                                <input
                                    class="form-check-input mt-1"
                                    type="radio"
                                    name="answers[<?= (int) $question['id'] ?>]"
                                    value="<?= e($optionText) ?>"
                                    id="<?= e($inputId) ?>"
                                    <?= $answerText === $optionText ? 'checked' : '' ?>
                                >
                                <span><?= e($optionText) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

            <?php else: ?>
                <div class="alert alert-danger mb-0">
                    Type de question non supporté : <?= e($type) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
};
?>

<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                    <div>
                        <div class="small text-secondary mb-1">Examen en cours</div>
                        <h1 class="h3 mb-1"><?= e((string) ($activeExam['title'] ?? 'Examen')) ?></h1>
                        <div class="text-secondary">
                            Code : <?= e((string) ($activeExam['code'] ?? '')) ?>
                            · Durée : <?= e((string) ($activeExam['duration_minutes'] ?? 0)) ?> min
                        </div>
                    </div>

                    <div class="text-lg-end">
                        <div class="badge text-bg-danger px-3 py-2 mb-2">
                            Statut : <?= e((string) ($activeExam['status'] ?? 'assigned')) ?>
                        </div>
                        <div class="small text-secondary">
                            Élève : <?= e((string) ($student['display_name'] ?? '')) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-4">
                <h2 class="h6 mb-3">Contexte poste</h2>

                <div class="small text-secondary mb-1">Poste</div>
                <div class="fw-semibold mb-3"><?= e((string) ($computer['name'] ?? 'Non détecté')) ?></div>

                <div class="small text-secondary mb-1">IP</div>
                <div class="fw-semibold mb-3"><?= e((string) ($network['ip'] ?? '')) ?></div>

                <div class="small text-secondary mb-1">Réseau</div>
                <div class="fw-semibold text-uppercase"><?= e((string) ($network['network_type'] ?? 'unknown')) ?></div>
            </div>
        </div>
    </div>
</div>

<form method="POST" action="<?= e(base_url('student/exam/submit')) ?>">
    <input type="hidden" name="_csrf" value="<?= e((string) ($csrf_exam_submit ?? '')) ?>">

    <?php if ($questions === []): ?>
        <div class="alert alert-warning">
            Aucun sujet généré pour cet examen.
        </div>
    <?php else: ?>
        <?php foreach ($questions as $question): ?>
            <?php $renderQuestionCard($question); ?>
        <?php endforeach; ?>

        <div class="d-flex justify-content-between align-items-center gap-3 mb-5">
            <a href="<?= e(base_url('student')) ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i>Retour
            </a>

            <button type="submit" class="btn btn-danger btn-lg">
                <i class="bi bi-send-check me-2"></i>Remettre l’examen
            </button>
        </div>
    <?php endif; ?>
</form>

<script>
window.ExamAppPage = {
    type: 'student-exam',
    heartbeatUrl: '<?= e(rtrim((string) \App\Core\Config::get('app.base_url', ''), '/') . '/api/student/heartbeat') ?>',
    csrfHeartbeat: '<?= e((string) ($csrf_heartbeat ?? '')) ?>',
    userExamId: <?= (int) ($activeExam['user_exam_id'] ?? 0) ?>
};
</script>