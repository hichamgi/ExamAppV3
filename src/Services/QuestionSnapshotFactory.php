<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class QuestionSnapshotFactory
{
    public function build(array $question, array $context = []): array
    {
        $metadata = $this->normalizeMetadata($question['metadata_array'] ?? []);
        $renderMode = (string) ($metadata['render_mode'] ?? '');

        return match ($renderMode) {
            'variable_qcm' => $this->buildVariableQcm($question, $metadata),
            'expression_qcm' => $this->buildExpressionQcm($question, $metadata),
            'ascii_from_class_name' => $this->buildAsciiFromClassName($question, $context),
            'schema_image' => $this->buildSchemaImage($question, $metadata),
            'algo_pascal_conversion' => $this->buildAlgoPascalConversion($question, $metadata),
            'inputs_blocks' => $this->buildInputsBlocks($question, $metadata),
            'algo_fill' => $this->buildAlgoFill($question, $metadata),
            'code_path' => $this->buildCodePath($question),
            default => $this->buildStaticQuestion($question),
        };
    }

    private function buildStaticQuestion(array $question): array
    {
        $questionText = (string) ($question['question_text'] ?? '');
        $type = (string) ($question['type'] ?? '');
        $options = [];

        foreach (($question['answer_options'] ?? []) as $option) {
            $options[] = (string) ($option['answer_text'] ?? '');
        }

        $correctAnswers = [];
        foreach (($question['answer_options'] ?? []) as $option) {
            if (!empty($option['is_correct'])) {
                $correctAnswers[] = (string) ($option['answer_text'] ?? '');
            }
        }

        return [
            'snapshot_json' => $this->encodeSnapshot([
                'q' => $questionText,
                't' => $type,
                'options' => $options,
            ]),
            'correct_answer_text' => $this->implodeCorrectAnswers($correctAnswers),
        ];
    }

    private function buildVariableQcm(array $question, array $metadata): array
    {
        $variables = $this->generateVariablesFromMetadata($metadata['variables'] ?? []);

        $questionText = $this->replacePlaceholders(
            (string) ($question['question_text'] ?? ''),
            $variables
        );

        $options = [];
        $correctAnswers = [];

        foreach (($question['answer_options'] ?? []) as $option) {
            $text = $this->replacePlaceholders(
                (string) ($option['answer_text'] ?? ''),
                $variables
            );

            $options[] = $text;

            if (!empty($option['is_correct'])) {
                $correctAnswers[] = $text;
            }
        }

        return [
            'snapshot_json' => $this->encodeSnapshot([
                'q' => $questionText,
                't' => (string) ($question['type'] ?? 'lists'),
                'options' => $options,
            ]),
            'correct_answer_text' => $this->implodeCorrectAnswers($correctAnswers),
        ];
    }

    private function buildExpressionQcm(array $question, array $metadata): array
    {
        $variables = $this->generateVariablesFromMetadata($metadata['variables'] ?? []);

        $questionText = $this->replaceExpressionTokens(
            (string) ($question['question_text'] ?? ''),
            $variables
        );

        $options = [];
        $correctAnswers = [];

        foreach (($question['answer_options'] ?? []) as $option) {
            $text = $this->replaceExpressionTokens(
                (string) ($option['answer_text'] ?? ''),
                $variables
            );

            $options[] = $text;

            if (!empty($option['is_correct'])) {
                $correctAnswers[] = $text;
            }
        }

        return [
            'snapshot_json' => $this->encodeSnapshot([
                'q' => $questionText,
                't' => (string) ($question['type'] ?? 'lists'),
                'options' => $options,
            ]),
            'correct_answer_text' => $this->implodeCorrectAnswers($correctAnswers),
        ];
    }

    private function buildAsciiFromClassName(array $question, array $context): array
    {
        $className = trim((string) ($context['class_name'] ?? ''));
        $binary = $this->stringToBinary($className);

        $questionText = rtrim((string) ($question['question_text'] ?? ''));
        if ($binary !== '') {
            $questionText .= PHP_EOL . $binary;
        }

        return [
            'snapshot_json' => $this->encodeSnapshot([
                'q' => $questionText,
                't' => (string) ($question['type'] ?? 'input'),
            ]),
            'correct_answer_text' => '',
        ];
    }

    private function buildSchemaImage(array $question, array $metadata): array
    {
        $imageSet = (string) ($metadata['image_set'] ?? '');
        $randomMin = (int) ($metadata['random_min'] ?? 1);
        $randomMax = (int) ($metadata['random_max'] ?? 1);

        if ($imageSet === '' || $randomMax < $randomMin) {
            throw new RuntimeException('Metadata schema_image invalide.');
        }

        $randomNumber = random_int($randomMin, $randomMax);

        $extension = str_starts_with($imageSet, 'Asus.PBZ77') ? 'jpg' : 'png';
        $imageFile = $imageSet . '-' . $randomNumber . '.' . $extension;

        $options = [];
        $correctAnswers = [];

        foreach (($question['answer_options'] ?? []) as $option) {
            $text = (string) ($option['answer_text'] ?? '');
            $options[] = $text;

            if (!empty($option['is_correct'])) {
                $correctAnswers[] = $text;
            }
        }

        return [
            'snapshot_json' => $this->encodeSnapshot([
                'q' => (string) ($question['question_text'] ?? ''),
                't' => (string) ($question['type'] ?? 'schema'),
                'image' => $imageFile,
                'options' => $options,
            ]),
            'correct_answer_text' => $this->implodeCorrectAnswers($correctAnswers),
        ];
    }

    private function buildAlgoPascalConversion(array $question, array $metadata): array
    {
        $source = (string) ($metadata['source'] ?? '');

        $algorithmText = match ($source) {
            'algo_p' => $this->getLegacyAlgoP(),
            default => '',
        };

        return [
            'snapshot_json' => $this->encodeSnapshot([
                'q' => (string) ($question['question_text'] ?? ''),
                't' => (string) ($question['type'] ?? 'textarea'),
                'algo' => $algorithmText,
                'note' => 'NB : Modifiez ligne par ligne',
            ]),
            'correct_answer_text' => '',
        ];
    }

    private function buildInputsBlocks(array $question, array $metadata): array
    {
        return [
            'snapshot_json' => $this->encodeSnapshot([
                'q' => (string) ($question['question_text'] ?? ''),
                't' => (string) ($question['type'] ?? 'inputs'),
                'render_mode' => 'inputs_blocks',
                'source' => (string) ($metadata['source'] ?? ''),
                'note' => 'À brancher sur l’ancien générateur blocGenerateur() pour retrouver exactement le legacy.',
            ]),
            'correct_answer_text' => '',
        ];
    }

    private function buildAlgoFill(array $question, array $metadata): array
    {
        return [
            'snapshot_json' => $this->encodeSnapshot([
                'q' => (string) ($question['question_text'] ?? ''),
                't' => (string) ($question['type'] ?? 'inputs'),
                'render_mode' => 'algo_fill',
                'source' => (string) ($metadata['source'] ?? ''),
                'input_count' => (int) ($metadata['input_count'] ?? 0),
                'note' => 'À brancher sur l’ancien générateur algogenerateur() pour retrouver exactement le legacy.',
            ]),
            'correct_answer_text' => '',
        ];
    }

    private function buildCodePath(array $question): array
    {
        return [
            'snapshot_json' => $this->encodeSnapshot([
                'q' => (string) ($question['question_text'] ?? ''),
                't' => (string) ($question['type'] ?? 'lists'),
                'render_mode' => 'code_path',
                'options' => array_map(
                    static fn(array $option): string => (string) ($option['answer_text'] ?? ''),
                    $question['answer_options'] ?? []
                ),
            ]),
            'correct_answer_text' => $this->implodeCorrectAnswers(
                array_map(
                    static fn(array $option): string => (string) ($option['answer_text'] ?? ''),
                    array_filter(
                        $question['answer_options'] ?? [],
                        static fn(array $option): bool => !empty($option['is_correct'])
                    )
                )
            ),
        ];
    }

    private function generateVariablesFromMetadata(array $variablesMetadata): array
    {
        $resolved = [];

        foreach ($variablesMetadata as $name => $rules) {
            if (!is_array($rules)) {
                continue;
            }

            $type = (string) ($rules['type'] ?? 'int');

            if ($type !== 'int') {
                throw new RuntimeException('Type de variable non supporté: ' . $type);
            }

            $min = (int) ($rules['min'] ?? 0);
            $max = (int) ($rules['max'] ?? 0);

            if ($max < $min) {
                throw new RuntimeException('Bornes invalides pour la variable ' . $name);
            }

            $value = random_int($min, $max);

            if (!empty($rules['distinct_from'])) {
                $other = (string) $rules['distinct_from'];
                if (isset($resolved[$other])) {
                    $guard = 0;
                    while ($value === $resolved[$other] && $guard < 50) {
                        $value = random_int($min, $max);
                        $guard++;
                    }
                }
            }

            $resolved[$name] = $value;
        }

        return $resolved;
    }

    private function replacePlaceholders(string $text, array $variables): string
    {
        foreach ($variables as $name => $value) {
            $text = str_replace('{' . $name . '}', (string) $value, $text);
        }

        return $text;
    }

    private function replaceExpressionTokens(string $text, array $variables): string
    {
        $text = $this->replacePlaceholders($text, $variables);

        if (isset($variables['k'])) {
            $k = (float) $variables['k'];

            $replacements = [
                '[2*k+1]' => $this->normalizeNumber((2 * $k) + 1),
                '[k]' => $this->normalizeNumber($k),
                '[k+0.5]' => $this->normalizeNumber($k + 0.5),
                '[k+1]' => $this->normalizeNumber($k + 1),
            ];

            $text = str_replace(array_keys($replacements), array_values($replacements), $text);

            $text = preg_replace('/\s*\{k=\d+~\d+\}/u', '', $text) ?? $text;
        }

        return trim($text);
    }

    private function normalizeMetadata(array $metadata): array
    {
        return is_array($metadata) ? $metadata : [];
    }

    private function encodeSnapshot(array $payload): string
    {
        return json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }

    private function implodeCorrectAnswers(array $answers): string
    {
        return implode(' | ', $answers);
    }

    private function normalizeNumber(float|int $value): string
    {
        if ((int) $value == $value) {
            return (string) ((int) $value);
        }

        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
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