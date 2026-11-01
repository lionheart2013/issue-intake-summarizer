<?php

namespace App\Services\Summary;

use App\Models\Issue;

interface SummaryGenerator
{
    /**
     * Produce a 1-2 sentence summary and a single concrete next action for the issue.
     * Implementations must be side-effect free and may throw on failure.
     */
    public function generate(Issue $issue): IssueSummary;
}
