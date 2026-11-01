<?php

namespace App\Services\Summary;

use App\Models\Issue;
use Illuminate\Support\Facades\Log;

final class FallbackSummaryGenerator implements SummaryGenerator
{
    public function __construct(
        private readonly SummaryGenerator $primary,
        private readonly SummaryGenerator $fallback,
    ) {}

    public function generate(Issue $issue): IssueSummary
    {
        try {
            return $this->primary->generate($issue);
        } catch (SummaryGenerationException $e) {
            Log::warning('LLM summary failed, using the rules-based fallback.', [
                'issue_id' => $issue->getKey(),
                'reason' => $e->getMessage(),
            ]);

            return $this->fallback->generate($issue);
        }
    }
}
