<?php

namespace App\Services\Summary;

final readonly class IssueSummary
{
    public function __construct(
        public string $summary,
        public string $suggestedNextAction,
    ) {}
}
