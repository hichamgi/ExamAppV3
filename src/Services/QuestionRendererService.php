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
            'algo_pascal_conversion' => $this->renderAlgoPascalConversion($normalizedQuestion),
            'inputs_blocks' => $this->renderInputsBlocks($normalizedQuestion),
            'algo_fill' => $this->renderAlgoFill($normalizedQuestion),
            'code_path' => $this->renderCodePath($normalizedQuestion),
            default => $this->renderStatic($normalizedQuestion),
        };

        $payload = $this->assertSnapshotIntegrity($payload);

        return [
            'snapshot' => $payload,
            'snapshot_json' => $this->encodeSnapshot($payload),
            'correct_answer_text' => $this->buildCorrectAnswerText($payload),
        ];
    }

    private function renderStatic(array $question): array
    {
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
        $className = trim((string) ($context['class_name'] ?? ''));
        $binary = $this->stringToBinary($className);

        $questionText = trim($question['question_text']);
        if ($binary !== '') {
            $questionText .= PHP_EOL . $binary;
        }

        return [
            'q' => $questionText,
            'type' => self::TYPE_INPUT,
            'options' => [],
        ];
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
        $metadata = $question['metadata'];
        $blocks = $metadata['blocks'] ?? [];
        $inputs = (int) ($metadata['inputs'] ?? count(is_array($blocks) ? $blocks : []));

        if (!is_array($blocks)) {
            $blocks = [];
        }

        return [
            'q' => $question['question_text'],
            'type' => self::TYPE_INPUTS,
            'inputs' => max(0, $inputs),
            'blocks' => array_values($blocks),
            'note' => $this->normalizeString($metadata['note'] ?? ''),
            'options' => [],
        ];
    }

    private function renderAlgoFill(array $question): array
    {
        $metadata = $question['metadata'];

        $algo = $this->normalizeString($metadata['algo'] ?? '');
        $inputs = (int) ($metadata['input_count'] ?? $metadata['inputs'] ?? 0);

        return [
            'q' => $question['question_text'],
            'type' => self::TYPE_INPUTS,
            'algo' => $algo,
            'inputs' => max(0, $inputs),
            'placeholders' => $this->normalizePlaceholders($metadata['placeholders'] ?? []),
            'note' => $this->normalizeString($metadata['note'] ?? ''),
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

    private function normalizeQuestion(array $question): array
    {
        $type = $this->normalizeString($question['type'] ?? self::TYPE_LISTS);
        if (!in_array($type, [
            self::TYPE_LISTS,
            self::TYPE_INPUT,
            self::TYPE_INPUTS,
            self::TYPE_TEXTAREA,
            self::TYPE_SCHEMA,
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
                'is_correct' => (bool) ($option['is_correct'] ?? false),
            ];
        }

        return [
            'id' => (int) ($question['id'] ?? 0),
            'question_text' => $this->normalizeString($question['question_text'] ?? ''),
            'type' => $type,
            'metadata' => $metadata,
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

        if (preg_match('/\[[^\]]+\]/u', $value) === 1) {
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