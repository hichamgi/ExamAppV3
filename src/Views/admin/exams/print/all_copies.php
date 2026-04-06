<?php
declare(strict_types=1);

/** @var array $copies */
/** @var int $exam_id */
/** @var int $class_id */

if (!function_exists('print_cell_value')) {
    function print_cell_value(mixed $value): string
    {
        $text = trim((string) $value);
        return $text !== '' ? nl2br(e($text), false) : '<span style="color:#777;">—</span>';
    }
}

if (!function_exists('print_tampon_svg')) {
    function print_tampon_svg(): string
    {
        $path = BASE_PATH . '/public/assets/img/tampon.svg';

        if (!is_file($path)) {
            return '';
        }

        $svg = file_get_contents($path);

        return $svg !== false ? $svg : '';
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Impression copies</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #111;
            background: #fff;
        }

        .toolbar {
            padding: 10px 12px;
            border-bottom: 1px solid #ccc;
            background: #f7f7f7;
        }

        .toolbar button {
            padding: 8px 14px;
            border: 1px solid #666;
            background: #fff;
            cursor: pointer;
            font-size: 12px;
        }

        .page {
            page-break-after: always;
            padding: 8px;
        }

        .page:last-child {
            page-break-after: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td,
        .header-table th,
        .copy-table td,
        .copy-table th,
        .signature-table td,
        .signature-table th {
            border: 1px solid #000;
            padding: 6px 8px;
            vertical-align: top;
        }

        .header-title {
            background: #e9e9e9;
            font-weight: 700;
            text-align: center;
        }

        .copy-table thead th {
            background: #efefef;
            text-align: center;
        }

        .text-center {
            text-align: center;
        }

        .question-col { width: 40%; }
        .answer-col { width: 25%; }
        .correct-col { width: 25%; }
        .points-col  { width: 10%; text-align: center; }

        .gap {
            height: 10px;
        }

        .signature-box {
            height: 85px;
        }

        .tampon-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 70px;
        }

        .tampon-wrap svg {
            max-width: 140px;
            max-height: 70px;
            width: auto;
            height: auto;
        }

        @media print {
            .toolbar {
                display: none;
            }

            body {
                margin: 0;
            }

            .page {
                padding: 0;
            }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <button type="button" onclick="window.print()">Imprimer</button>
</div>

<?php foreach ($copies as $copy): ?>
    <?php
    $student = $copy['student'] ?? [];
    $questions = $copy['questions'] ?? [];
    $studentName = trim((string) (($student['nom'] ?? '') . ' ' . ($student['prenom'] ?? '')));
    ?>
    <div class="page">

        <table class="header-table">
            <tr>
                <td class="header-title" colspan="2">ExamAppV3 - Copie élève</td>
                <td class="header-title" colspan="2">Impression administrative</td>
            </tr>
            <tr>
                <td style="width:15%;"><strong>Examen ID :</strong></td>
                <td><?= (int) $exam_id ?></td>
                <td style="width:15%;"><strong>Classe :</strong></td>
                <td><?= e($student['class_name'] ?? '') ?></td>
            </tr>
            <tr>
                <td><strong>Élève :</strong></td>
                <td><?= e($studentName) ?></td>
                <td><strong>Code Massar :</strong></td>
                <td><?= e($student['code_massar'] ?? '') ?></td>
            </tr>
            <tr>
                <td><strong>Numéro :</strong></td>
                <td><?= (int) ($student['numero'] ?? 0) ?></td>
                <td><strong>Note :</strong></td>
                <td><strong><?= e(number_format((float) ($student['score'] ?? 0), 2, '.', '')) ?></strong></td>
            </tr>
        </table>

        <div class="gap"></div>

        <table class="copy-table">
            <thead>
                <tr>
                    <th style="width:6%;">#</th>
                    <th class="question-col">Question</th>
                    <th class="answer-col">Réponse élève</th>
                    <th class="correct-col">Réponse correcte</th>
                    <th class="points-col">Pts</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($questions)): ?>
                    <tr>
                        <td colspan="5" class="text-center">Aucune réponse enregistrée.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($questions as $q): ?>
                        <tr>
                            <td class="text-center"><?= (int) ($q['question_num'] ?? 0) ?></td>
                            <td><?= print_cell_value($q['question_text'] ?? '') ?></td>
                            <td><?= print_cell_value($q['student_answer'] ?? '') ?></td>
                            <td><?= print_cell_value($q['correct_answer'] ?? '') ?></td>
                            <td class="points-col"><?= e(number_format((float) ($q['awarded_points'] ?? 0), 2, '.', '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="gap"></div>

        <table class="signature-table">
            <tr>
                <th style="width:45%;">Observation</th>
                <th style="width:30%;">Tampon / Signature</th>
                <th style="width:25%;">Visa</th>
            </tr>
            <tr>
                <td class="signature-box"></td>
                <td class="signature-box">
                    <div class="tampon-wrap">
                        <?= print_tampon_svg() ?>
                    </div>
                </td>
                <td class="signature-box"></td>
            </tr>
        </table>

    </div>
<?php endforeach; ?>

</body>
</html>