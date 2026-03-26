<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class QuestionRendererService
{
    private const TYPE_LISTS = 'lists';
    private const TYPE_INPUT = 'input';
    private const TYPE_INPUTS = 'inputs';
    private const TYPE_TEXTAREA = 'textarea';
    private const TYPE_SCHEMA = 'schema';
    private const TYPE_CP = 'cp';

    public function build(array $question, array $context = []): array
    {
        $normalizedQuestion = $this->normalizeQuestion($question);
        $metadata = $normalizedQuestion['metadata'];

        $renderMode = $this->normalizeString($metadata['render_mode'] ?? '');

        $payload = match ($renderMode) {
            'variable_qcm' => $this->renderVariableQcm($normalizedQuestion),
            'expression_qcm' => $this->renderExpressionQcm($normalizedQuestion),
            'ascii_from_class_name' => $this->renderAsciiFromClassName($normalizedQuestion, $context),
            'schema_image' => $this->renderSchemaImage($normalizedQuestion),
            'schema_mapping' => $this->renderSchemaMapping($normalizedQuestion),
            'algo_pascal_conversion' => $this->renderAlgoPascalConversion($normalizedQuestion),
            'inputs_blocks' => $this->renderInputsBlocks($normalizedQuestion),
            'algo_fill' => $this->renderAlgoFill($normalizedQuestion),
            'code_path' => $this->renderCodePath($normalizedQuestion),
            'rom_types_input' => $this->renderRomTypesInput($normalizedQuestion),
            default => $this->renderStatic($normalizedQuestion),
        };

        $payload = $this->assertSnapshotIntegrity($payload);

        return [
            'snapshot' => $payload,
            'snapshot_json' => $this->encodeSnapshot($payload),
            'correct_answer_text' => $this->buildCorrectAnswerText($payload),
        ];
    }

    private function renderRomTypesInput(array $question): array
    {
        $metadata = $question['metadata'];

        $expectedItems = isset($metadata['expected_items']) && is_array($metadata['expected_items'])
            ? array_values(array_filter(
                array_map(fn($item): string => $this->normalizeString($item), $metadata['expected_items']),
                fn(string $item): bool => $item !== ''
            ))
            : [];

        if ($expectedItems === []) {
            throw new RuntimeException('rom_types_input: expected_items manquant ou vide.');
        }

        $pointsPerItem = (float) ($metadata['points_per_item'] ?? 5);
        $maxScore = (float) ($metadata['max_score'] ?? 20);
        $correctionPolicy = $this->normalizeString($metadata['correction_policy'] ?? 'lenient');
        $caseSensitive = (bool) ($metadata['case_sensitive'] ?? false);
        $deduplicate = (bool) ($metadata['deduplicate'] ?? true);

        return [
            'q' => $question['question_text'],
            'type' => self::TYPE_INPUT,
            'expected_items' => $expectedItems,
            'correction_mode' => 'item_list_flexible',
            'points_per_item' => $pointsPerItem,
            'max_score' => $maxScore,
            'correction_policy' => $correctionPolicy !== '' ? $correctionPolicy : 'lenient',
            'case_sensitive' => $caseSensitive,
            'deduplicate' => $deduplicate,
            'options' => [],
        ];
    }

    private function renderStatic(array $question): array
    {
        if ($question['type'] === self::TYPE_CP) {
            return $this->renderCpQuestion($question);
        }

        return [
            'q' => $question['question_text'],
            'type' => $question['type'],
            'options' => $this->buildSnapshotOptions($question['answer_options']),
        ];
    }

    private function renderVariableQcm(array $question): array
    {
        $variables = $this->generateVariables($question['metadata']['variables'] ?? []);
        $questionText = $this->interpolatePlaceholders($question['question_text'], $variables);

        $options = [];
        foreach ($question['answer_options'] as $option) {
            $options[] = [
                'text' => $this->interpolatePlaceholders($option['answer_text'], $variables),
                'correct' => $option['is_correct'],
            ];
        }

        return [
            'q' => $questionText,
            'type' => $question['type'],
            'options' => $options,
        ];
    }

    private function renderExpressionQcm(array $question): array
    {
        $variables = $this->generateVariables($question['metadata']['variables'] ?? []);
        $questionText = $this->interpolateExpressions(
            $this->interpolatePlaceholders($question['question_text'], $variables),
            $variables
        );

        $options = [];
        foreach ($question['answer_options'] as $option) {
            $options[] = [
                'text' => $this->interpolateExpressions(
                    $this->interpolatePlaceholders($option['answer_text'], $variables),
                    $variables
                ),
                'correct' => $option['is_correct'],
            ];
        }

        return [
            'q' => $questionText,
            'type' => $question['type'],
            'options' => $options,
        ];
    }

    private function renderAsciiFromClassName(array $question, array $context): array
    {
        $sourceText = $this->buildAsciiSourceTextFromClassName(
            (string) ($context['class_name'] ?? '')
        );

        $binary = $this->stringToBinary($sourceText);

        $questionText = trim($question['question_text']);
        if ($binary !== '') {
            $questionText .= PHP_EOL . $binary;
        }

        return [
            'q' => $questionText,
            'type' => self::TYPE_INPUT,
            'expected_text' => $sourceText,
            'correction_mode' => 'per_character_position',
            'points_per_char' => 1,
            'options' => [],
        ];
    }

    private function buildAsciiSourceTextFromClassName(string $className): string
    {
        $normalized = strtoupper(trim($className));
        $prefix = $this->resolveAsciiPrefixFromClassName($normalized);
        $styledPrefix = $this->randomizeAsciiPrefixCase($prefix);
        $digit = (string) random_int(1, 9);

        return $styledPrefix . ' ' . $digit;
    }

    private function resolveAsciiPrefixFromClassName(string $className): string
    {
        if (str_starts_with($className, 'TCT')) {
            return 'tct';
        }

        if (str_starts_with($className, 'TCSF') || str_starts_with($className, 'TCS')) {
            return 'tcs';
        }

        if (str_starts_with($className, 'TCLSHF') || str_starts_with($className, 'TCL')) {
            return 'tcl';
        }

        throw new RuntimeException('Type de classe non supporté pour ascii_from_class_name.');
    }

    private function randomizeAsciiPrefixCase(string $prefix): string
    {
        $letters = str_split(strtolower($prefix));

        if (count($letters) !== 3) {
            throw new RuntimeException('Préfixe ASCII invalide.');
        }

        $mode = random_int(0, 1);

        if ($mode === 0) {
            // 2 maj + 1 min
            $lowerIndex = random_int(0, 2);

            foreach ($letters as $index => $letter) {
                $letters[$index] = ($index === $lowerIndex)
                    ? strtolower($letter)
                    : strtoupper($letter);
            }
        } else {
            // 2 min + 1 maj
            $upperIndex = random_int(0, 2);

            foreach ($letters as $index => $letter) {
                $letters[$index] = ($index === $upperIndex)
                    ? strtoupper($letter)
                    : strtolower($letter);
            }
        }

        return implode('', $letters);
    }

    private function renderSchemaImage(array $question): array
    {
        $metadata = $question['metadata'];
        $image = $this->resolveSchemaImage($metadata);

        return [
            'q' => $question['question_text'],
            'type' => self::TYPE_SCHEMA,
            'image' => $image,
            'options' => $this->buildSnapshotOptions($question['answer_options']),
        ];
    }

    private function renderSchemaMapping(array $question): array
    {
        $metadata = $question['metadata'];

        $imageSet = $this->normalizeString($metadata['image_set'] ?? '');
        $schemaMin = (int) ($metadata['random_min'] ?? 1);
        $schemaMax = (int) ($metadata['random_max'] ?? 1);
        $lettersCount = (int) ($metadata['letters_count'] ?? 6);
        $pointsPerInput = (float) ($metadata['points_per_input'] ?? 1);

        if ($imageSet === '') {
            throw new RuntimeException('schema_mapping: image_set manquant.');
        }

        if ($schemaMax < $schemaMin) {
            throw new RuntimeException('schema_mapping: bornes invalides.');
        }

        if ($lettersCount <= 0) {
            $lettersCount = 6;
        }

        $schemaNumber = random_int($schemaMin, $schemaMax);
        $mappingByLetter = $this->buildSchemaMappingFromOptions($question['answer_options'], $schemaNumber);

        if ($mappingByLetter === []) {
            throw new RuntimeException('schema_mapping: aucun mapping pour le schéma ' . $schemaNumber . '.');
        }

        $availableLetters = array_keys($mappingByLetter);
        sort($availableLetters, SORT_STRING);

        if (count($availableLetters) < $lettersCount) {
            throw new RuntimeException('schema_mapping: lettres insuffisantes pour le schéma ' . $schemaNumber . '.');
        }

        shuffle($availableLetters);
        $selectedLetters = array_slice($availableLetters, 0, $lettersCount);
        sort($selectedLetters, SORT_STRING);

        $expectedAnswers = [];
        foreach ($selectedLetters as $letter) {
            if (!isset($mappingByLetter[$letter])) {
                throw new RuntimeException('schema_mapping: mapping manquant pour la lettre ' . $letter . '.');
            }

            $expectedAnswers[] = $mappingByLetter[$letter];
        }

        return [
            'q' => $question['question_text'],
            'n' => $schemaNumber,
            'image' => $this->buildSchemaImageFilename($imageSet, $schemaNumber, $metadata),
            'type' => self::TYPE_INPUTS,
            'inputs' => count($selectedLetters),
            'letters' => array_values($selectedLetters),
            'choices' => $this->buildSchemaChoices($question['answer_options']),
            'expected' => $expectedAnswers,
            'points_per_input' => $pointsPerInput,
            'options' => [],
        ];
    }

    private function renderAlgoPascalConversion(array $question): array
    {
        $metadata = $question['metadata'];

        $algo = $this->normalizeString($metadata['algo'] ?? '');
        if ($algo === '') {
            $source = $this->normalizeString($metadata['source'] ?? '');
            $algo = match ($source) {
                'algo_p' => $this->getLegacyAlgoP(),
                default => '',
            };
        }

        return [
            'q' => $question['question_text'],
            'type' => self::TYPE_TEXTAREA,
            'algo' => $algo,
            'note' => $this->normalizeString($metadata['note'] ?? 'NB : Modifiez ligne par ligne'),
            'options' => [],
        ];
    }

    private function renderInputsBlocks(array $question): array
    {
        $generated = $this->generateLegacyBlocks();

        return [
            'q' => $question['question_text'],
            'type' => self::TYPE_INPUTS,
            'inputs' => 6,
            'html' => $generated['html'],
            'expected' => $generated['expected'],
            'points_per_input' => (float) ($question['points'] / 6),
            'options' => [],
        ];
    }

    private function renderAlgoFill(array $question): array
    {
        $generated = $this->generateLegacyAlgoFill();

        return [
            'q' => $question['question_text'],
            'type' => self::TYPE_INPUTS,
            'inputs' => 8,
            'html' => $generated['html'],
            'expected' => $generated['expected'],
            'points_per_input' => (float) ($question['points'] / 8),
            'options' => [],
        ];
    }

    private function renderCodePath(array $question): array
    {
        return [
            'q' => $question['question_text'],
            'type' => self::TYPE_LISTS,
            'options' => $this->buildSnapshotOptions($question['answer_options']),
        ];
    }

    private function renderCpQuestion(array $question): array
    {
        $metadata = $question['metadata'];

        $cpFields = isset($metadata['cp_fields']) && is_array($metadata['cp_fields'])
            ? array_values($metadata['cp_fields'])
            : [
                [
                    'key' => 'topology',
                    'kind' => 'select',
                    'label' => 'Topologie',
                    'choices' => ['Bus', 'Etoile', 'Anneau'],
                ],
                [
                    'key' => 'twisted_pair',
                    'kind' => 'number',
                    'label' => 'Longueur de la paire torsadée en m',
                ],
                [
                    'key' => 'coax',
                    'kind' => 'number',
                    'label' => 'Longueur du câble coaxial en m',
                ],
                [
                    'key' => 'rj45',
                    'kind' => 'number',
                    'label' => 'Nombre de RJ45',
                ],
                [
                    'key' => 'bnc',
                    'kind' => 'number',
                    'label' => 'Nombre de BNC',
                ],
                [
                    'key' => 'hub',
                    'kind' => 'number',
                    'label' => 'Nombre de Hub',
                ],
                [
                    'key' => 'switch',
                    'kind' => 'number',
                    'label' => 'Nombre de Switch',
                ],
                [
                    'key' => 't_connector',
                    'kind' => 'number',
                    'label' => 'Nombre de Connecteur en T',
                ],
                [
                    'key' => 'terminator',
                    'kind' => 'number',
                    'label' => 'Nombre de Bouchon',
                ],
            ];

        $cpRules = isset($metadata['cp_rules']) && is_array($metadata['cp_rules'])
            ? $metadata['cp_rules']
            : [];

        return [
            'q' => $question['question_text'],
            'type' => self::TYPE_CP,
            'cp_fields' => $cpFields,
            'cp_rules' => $cpRules,
            'blank_numeric_as_zero' => (bool) ($metadata['blank_numeric_as_zero'] ?? true),
            'max_score' => (float) ($metadata['max_score'] ?? 20),
            'options' => [],
        ];
    }

    private function normalizeQuestion(array $question): array
    {
        $type = $this->normalizeString($question['type'] ?? self::TYPE_LISTS);
        if (!in_array($type, [
            self::TYPE_LISTS,
            self::TYPE_INPUT,
            self::TYPE_INPUTS,
            self::TYPE_TEXTAREA,
            self::TYPE_SCHEMA,
            self::TYPE_CP,
        ], true)) {
            $type = self::TYPE_LISTS;
        }

        $metadata = $question['metadata_array'] ?? [];
        if (!is_array($metadata)) {
            $metadata = [];
        }

        $answerOptions = [];
        foreach (($question['answer_options'] ?? []) as $option) {
            if (!is_array($option)) {
                continue;
            }

            $answerOptions[] = [
                'answer_text' => $this->normalizeString($option['answer_text'] ?? ''),
                'explanation' => $this->normalizeString($option['explanation'] ?? ''),
                'is_correct' => (bool) ($option['is_correct'] ?? false),
            ];
        }

        return [
            'id' => (int) ($question['id'] ?? 0),
            'question_text' => $this->normalizeString($question['question_text'] ?? ''),
            'type' => $type,
            'metadata' => $metadata,
            'points' => (float) ($question['points'] ?? 0),
            'answer_options' => $answerOptions,
        ];
    }

    private function buildSnapshotOptions(array $answerOptions): array
    {
        $options = [];

        foreach ($answerOptions as $option) {
            $options[] = [
                'text' => $this->normalizeString($option['answer_text'] ?? ''),
                'correct' => (bool) ($option['is_correct'] ?? false),
            ];
        }

        return $options;
    }

    private function buildSchemaMappingFromOptions(array $answerOptions, int $schemaNumber): array
    {
        $mapping = [];

        foreach ($answerOptions as $option) {
            $label = $this->normalizeString($option['answer_text'] ?? '');
            $explanation = $this->normalizeString($option['explanation'] ?? '');

            if ($label === '' || $explanation === '') {
                continue;
            }

            $pairs = array_filter(array_map('trim', explode(',', $explanation)));

            foreach ($pairs as $pair) {
                if (!preg_match('/^(\d+)\.([A-Z])$/', $pair, $matches)) {
                    continue;
                }

                $currentSchema = (int) $matches[1];
                $letter = (string) $matches[2];

                if ($currentSchema !== $schemaNumber) {
                    continue;
                }

                $mapping[$letter] = $label;
            }
        }

        ksort($mapping, SORT_STRING);

        return $mapping;
    }

    private function buildSchemaChoices(array $answerOptions): array
    {
        $choices = [];

        foreach ($answerOptions as $option) {
            $text = $this->normalizeString($option['answer_text'] ?? '');
            if ($text !== '') {
                $choices[] = $text;
            }
        }

        sort($choices, SORT_STRING);

        return array_values(array_unique($choices));
    }

    private function buildSchemaImageFilename(string $imageSet, int $schemaNumber, array $metadata): string
    {
        $extension = $this->normalizeString($metadata['extension'] ?? '');
        if ($extension === '') {
            $extension = str_starts_with($imageSet, 'Asus.PBZ77') ? 'jpg' : 'png';
        }

        return $imageSet . '-' . $schemaNumber . '.' . ltrim($extension, '.');
    }

    private function generateLegacyBlocks(): array
    {
        $first = $this->legacyBlockGenerator(1);
        $second = $this->legacyBlockGenerator(2);

        return [
            'html' => $first['qst'] . $second['qst'],
            'expected' => array_merge($first['expected'], $second['expected']),
        ];
    }

    private function legacyBlockGenerator(int $n): array
    {
        do {
            $a1 = random_int(10, 20);
            $a2 = random_int(2, 9);
            $a3 = random_int(2, 9);
            $a4 = random_int(2, 9);
            $a5 = random_int(2, 9);
            $a6 = random_int(2, 9);
            $a7 = random_int(2, 9);
            $a8 = random_int(2, 9);
            $a9 = random_int(2, 9);
            $a10 = random_int(2, 9);

            $A1 = $a1 + $a2 * $a3 + ($a4 - $a5 * $a6) / $a7;
            $A2 = $A1 / $a8 - $a9;
            $A3 = $A2 / $a10;
        } while (
            !$this->isWholeNumber($A1) ||
            !$this->isWholeNumber($A2) ||
            !$this->isWholeNumber($A3) ||
            $A1 <= 0 || $A2 <= 0 || $A3 <= 0 ||
            $A1 >= 50 || $A2 >= 50 || $A3 >= 50
        );

        return [
            'qst' =>
                "<br>A={$a1}+{$a2}*{$a3}+({$a4}-{$a5}*{$a6})/{$a7} [__INPUT_{$n}_1__]" .
                "<br>A=A/{$a8}-{$a9} [__INPUT_{$n}_2__]" .
                "<br>A=A/{$a10} [__INPUT_{$n}_3__]<br>",
            'expected' => [
                (string) ((int) $A1),
                (string) ((int) $A2),
                (string) ((int) $A3),
            ],
        ];
    }

    private function generateLegacyAlgoFill(): array
    {
        $choix1 = random_int(0, 3);
        $choix2 = random_int(0, 3);

        $remise1 = [5, 10, 15, 20];
        $remise2 = [10, 20, 20, 25];
        $remise3 = [15, 30, 28, 30];
        $nbrArt1 = [10, 20, 50, 100];
        $nbrArt2 = [20, 50, 100, 300];

        if ($choix1 >= $choix2) {
            $remiseCal3 = $remise1[$choix1] / 100;
            $remiseCal2 = $remise2[$choix1] / 100;
            $remiseCal1 = $remise3[$choix1] / 100;
            $expected = [
                'VARIABLE',
                'ENTIER',
                'NBR',
                'TVA',
                'NBR<=' . $nbrArt1[$choix2],
                'SINON',
                'NBR<=' . $nbrArt2[$choix2],
                'PTTC',
            ];
        } else {
            $remiseCal1 = $remise1[$choix1] / 100;
            $remiseCal2 = $remise2[$choix1] / 100;
            $remiseCal3 = $remise3[$choix1] / 100;
            $expected = [
                'VARIABLE',
                'ENTIER',
                'NBR',
                'TVA',
                'NBR>' . $nbrArt2[$choix2],
                'SINON',
                'NBR>' . $nbrArt1[$choix2],
                'PTTC',
            ];
        }

        $html = "Complétez l’algorithme suivant : L’algorithme permet de calculer le Prix TTC (PTTC) à partir du Prix Hors Taxe Unitaire (PHTU), le nombre d’article (Nbr) et la TVA selon la règle suivante :"
            . "<table class='table table-striped table-sm'>"
            . "<thead><tr><th>Remise</th><th>Condition</th></tr></thead>"
            . "<tbody>"
            . "<tr><td>{$remise1[$choix1]}%</td><td>nombre d’article ≤ {$nbrArt1[$choix2]}</td></tr>"
            . "<tr><td>{$remise2[$choix1]}%</td><td>{$nbrArt1[$choix2]} < nombre d’article ≤ {$nbrArt2[$choix2]}</td></tr>"
            . "<tr><td>{$remise3[$choix1]}%</td><td>nombre d’article > {$nbrArt2[$choix2]}</td></tr>"
            . "</tbody></table>"
            . "ALGORITHME Facture ;<br>"
            . "[__INPUT_1__]<br>"
            . "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Nbr : [__INPUT_2__] ;<br>"
            . "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;PHTU, PTTC, TVA : REEL ;<br>"
            . "DEBUT<br>"
            . "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;ECRIRE('Donnez le prix Hors Taxe, le nombre d article et TVA : ') ;<br>"
            . "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;LIRE(PHTU , [__INPUT_3__] , [__INPUT_4__] ) ;<br>"
            . "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;SI [__INPUT_5__] ALORS<br>"
            . "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;PTTC ← PHTU * Nbr * (1 + TVA - {$remiseCal3}) ;<br>"
            . "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;[__INPUT_6__]<br>"
            . "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;SI [__INPUT_7__] ALORS<br>"
            . "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;PTTC ← PHTU * Nbr * (1 + TVA - {$remiseCal2}) ;<br>"
            . "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;SINON<br>"
            . "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;PTTC ← PHTU * Nbr * (1 + TVA - {$remiseCal1}) ;<br>"
            . "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;FINSI<br>"
            . "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;FINSI<br>"
            . "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;ECRIRE('Le prix TTC = ', [__INPUT_8__] ) ;<br>"
            . "FIN<br>";

        return [
            'html' => $html,
            'expected' => $expected,
        ];
    }

    private function generateVariables(array $definitions): array
    {
        if (!is_array($definitions)) {
            return [];
        }

        $resolved = [];

        foreach ($definitions as $name => $definition) {
            if (!is_string($name) || !is_array($definition)) {
                continue;
            }

            $type = $this->normalizeString($definition['type'] ?? 'int');
            if ($type !== 'int') {
                throw new RuntimeException('Type de variable non supporté: ' . $type);
            }

            $min = (int) ($definition['min'] ?? 0);
            $max = (int) ($definition['max'] ?? 0);

            if ($max < $min) {
                throw new RuntimeException('Bornes invalides pour la variable ' . $name);
            }

            $value = random_int($min, $max);

            $distinctFrom = $this->normalizeString($definition['distinct_from'] ?? '');
            if ($distinctFrom !== '' && array_key_exists($distinctFrom, $resolved)) {
                if ($max === $min && $resolved[$distinctFrom] === $value) {
                    throw new RuntimeException('Variable distinct_from impossible à résoudre pour ' . $name);
                }

                $guard = 0;
                while ($value === $resolved[$distinctFrom]) {
                    $value = random_int($min, $max);
                    $guard++;

                    if ($guard > 100) {
                        throw new RuntimeException('Impossible de résoudre distinct_from pour ' . $name);
                    }
                }
            }

            $resolved[$name] = $value;
        }

        return $resolved;
    }

    private function interpolatePlaceholders(string $template, array $variables): string
    {
        return (string) preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/u',
            function (array $matches) use ($variables): string {
                $name = $matches[1];

                if (!array_key_exists($name, $variables)) {
                    throw new RuntimeException('Variable non résolue dans le snapshot: {' . $name . '}');
                }

                return $this->normalizeScalar($variables[$name]);
            },
            $template
        );
    }

    private function interpolateExpressions(string $template, array $variables): string
    {
        return (string) preg_replace_callback(
            '/\[([^\]]+)\]/u',
            function (array $matches) use ($variables): string {
                $expression = trim($matches[1]);

                return $this->evaluateSupportedExpression($expression, $variables);
            },
            $template
        );
    }

    private function evaluateSupportedExpression(string $expression, array $variables): string
    {
        if ($expression === '') {
            throw new RuntimeException('Expression vide dans le snapshot.');
        }

        if ($expression === 'k') {
            return $this->normalizeNumber((float) ($variables['k'] ?? 0));
        }

        if ($expression === 'k+1') {
            return $this->normalizeNumber(((float) ($variables['k'] ?? 0)) + 1);
        }

        if ($expression === 'k+0.5') {
            return $this->normalizeNumber(((float) ($variables['k'] ?? 0)) + 0.5);
        }

        if ($expression === '2*k+1') {
            return $this->normalizeNumber((2 * ((float) ($variables['k'] ?? 0))) + 1);
        }

        throw new RuntimeException('Expression non supportée dans le snapshot: [' . $expression . ']');
    }

    private function resolveSchemaImage(array $metadata): string
    {
        $images = $metadata['images'] ?? null;
        if (is_array($images) && $images !== []) {
            $index = array_rand($images);
            return $this->normalizeString($images[$index] ?? '');
        }

        $imageSet = $this->normalizeString($metadata['image_set'] ?? '');
        $min = (int) ($metadata['random_min'] ?? 1);
        $max = (int) ($metadata['random_max'] ?? 1);

        if ($imageSet === '' || $max < $min) {
            throw new RuntimeException('Metadata schema_image invalide.');
        }

        $number = random_int($min, $max);
        $extension = $this->normalizeString($metadata['extension'] ?? '');

        if ($extension === '') {
            $extension = str_starts_with($imageSet, 'Asus.PBZ77') ? 'jpg' : 'png';
        }

        return $imageSet . '-' . $number . '.' . $extension;
    }

    private function normalizePlaceholders(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $item) {
            if (!is_scalar($item)) {
                continue;
            }

            $normalized[] = trim((string) $item);
        }

        return $normalized;
    }

    private function buildCorrectAnswerText(array $snapshot): string
    {
        if (($snapshot['type'] ?? '') === self::TYPE_INPUTS && isset($snapshot['expected']) && is_array($snapshot['expected'])) {
            return json_encode(
                array_values($snapshot['expected']),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ) ?: '';
        }

        $correctAnswers = [];

        $options = $snapshot['options'] ?? [];
        if (is_array($options)) {
            foreach ($options as $option) {
                if (!is_array($option)) {
                    continue;
                }

                if (!empty($option['correct'])) {
                    $text = $this->normalizeString($option['text'] ?? '');
                    if ($text !== '') {
                        $correctAnswers[] = $text;
                    }
                }
            }
        }

        return implode(' | ', $correctAnswers);
    }

    private function assertSnapshotIntegrity(array $payload): array
    {
        $questionText = $this->normalizeString($payload['q'] ?? '');
        if ($questionText === '') {
            throw new RuntimeException('Snapshot invalide: question vide.');
        }

        $type = $this->normalizeString($payload['type'] ?? '');
        if ($type === '') {
            throw new RuntimeException('Snapshot invalide: type vide.');
        }

        $this->assertNoForbiddenTokens($questionText);

        if (isset($payload['options']) && is_array($payload['options'])) {
            foreach ($payload['options'] as $index => $option) {
                if (!is_array($option)) {
                    throw new RuntimeException('Snapshot invalide: option #' . $index . ' invalide.');
                }

                $text = $this->normalizeString($option['text'] ?? '');
                $this->assertNoForbiddenTokens($text);

                $payload['options'][$index] = [
                    'text' => $text,
                    'correct' => (bool) ($option['correct'] ?? false),
                ];
            }
        } else {
            $payload['options'] = [];
        }

        if (isset($payload['letters']) && !is_array($payload['letters'])) {
            throw new RuntimeException('Snapshot invalide: letters doit être un tableau.');
        }

        if (isset($payload['expected']) && !is_array($payload['expected'])) {
            throw new RuntimeException('Snapshot invalide: expected doit être un tableau.');
        }

        return $payload;
    }

    private function assertNoForbiddenTokens(string $value): void
    {
        if ($value === '') {
            return;
        }

        if (preg_match('/\{[a-zA-Z_][a-zA-Z0-9_]*\}/u', $value) === 1) {
            throw new RuntimeException('Snapshot invalide: variable non résolue détectée.');
        }

        if (preg_match('/\[[^\]]+\]/u', $value) === 1 && !preg_match('/^\[__INPUT_\d+(?:_\d+)?__\]$/u', $value)) {
            throw new RuntimeException('Snapshot invalide: expression non résolue détectée.');
        }

        if (preg_match('/\[(bloc|algo)\d+\]/iu', $value) === 1) {
            throw new RuntimeException('Snapshot invalide: marqueur legacy détecté.');
        }

        if (preg_match('/\[img=.*?\]/iu', $value) === 1) {
            throw new RuntimeException('Snapshot invalide: image legacy détectée.');
        }

        if (preg_match('/\[ascii\]/iu', $value) === 1) {
            throw new RuntimeException('Snapshot invalide: ascii legacy détecté.');
        }
    }

    private function encodeSnapshot(array $payload): string
    {
        return json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }

    private function normalizeString(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function normalizeScalar(mixed $value): string
    {
        if (is_int($value) || is_float($value)) {
            return $this->normalizeNumber($value);
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return is_scalar($value) ? (string) $value : '';
    }

    private function normalizeNumber(float|int $value): string
    {
        if ((float) ((int) $value) === (float) $value) {
            return (string) ((int) $value);
        }

        return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
    }

    private function stringToBinary(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $chars = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars === false) {
            return '';
        }

        $parts = [];

        foreach ($chars as $char) {
            $code = mb_ord($char, 'UTF-8');
            if ($code === false) {
                continue;
            }

            $parts[] = str_pad(decbin($code), 8, '0', STR_PAD_LEFT);
        }

        return implode(' ', $parts);
    }

    private function isWholeNumber(float|int $value): bool
    {
        return floor((float) $value) === (float) $value;
    }

    private function getLegacyAlgoP(): string
    {
        return "Algorithme Facture ;\n"
            . "Variable\n"
            . "      copie : Entier ;\n"
            . "      prix : Reel;\n"
            . "DEBUT\n"
            . "      ECRIRE('Donnez le nombre de copie');\n"
            . "      LIRE(copie);\n"
            . "      SI copie<=0 ALORS\n"
            . "            ECRIRE('Erreur : nombre de copie doit etre > 0');\n"
            . "      SINON\n"
            . "            SI copie <=10 ALORS\n"
            . "                  prix <- copie*0.30;\n"
            . "            SINON\n"
            . "                  SI copie<=30 ALORS\n"
            . "                        prix <- 10*0.30 + (copie-10)*0.25;\n"
            . "                  SINON\n"
            . "                        prix <- 10*0.30+20*0.25+(copie-30)*0.20;\n"
            . "                  FINSI\n"
            . "            FINSI\n"
            . "            ECRIRE('Prix a payer : ', prix);\n"
            . "      FINSI\n"
            . "FIN";
    }
}