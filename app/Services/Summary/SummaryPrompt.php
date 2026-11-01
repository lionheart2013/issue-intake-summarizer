<?php

namespace App\Services\Summary;

use App\Models\Issue;
use Illuminate\Support\Facades\File;

final class SummaryPrompt
{
    public function __construct(private readonly string $templatePath) {}

    public function render(Issue $issue): string
    {
        // strtr replaces in a single pass, so placeholders typed into an issue stay literal.
        return strtr(File::get($this->templatePath), [
            '{{title}}' => $issue->title,
            '{{priority}}' => $issue->priority->value,
            '{{category}}' => $issue->category->value,
            '{{description}}' => $issue->description,
        ]);
    }
}
