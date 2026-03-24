<?php

declare(strict_types=1);

namespace App\Services;

final class QuestionSnapshotFactory
{
    private QuestionRendererService $renderer;

    public function __construct()
    {
        $this->renderer = new QuestionRendererService();
    }

    public function build(array $question, array $context = []): array
    {
        return $this->renderer->build($question, $context);
    }
}