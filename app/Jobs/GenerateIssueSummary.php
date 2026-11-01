<?php

namespace App\Jobs;

use App\Enums\SummaryStatus;
use App\Models\Issue;
use App\Services\Summary\SummaryGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateIssueSummary implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 90;


    /** @var array<int, int> */
    public array $backoff = [10, 60];

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Issue $issue) {}

    public function handle(SummaryGenerator $generator): void
    {
        $result = $generator->generate($this->issue);

        $this->issue->forceFill([
            'summary' => $result->summary,
            'suggested_next_action' => $result->suggestedNextAction,
            'summary_status' => SummaryStatus::Ready,
        ])->save();
    }

    public function failed(Throwable $exception): void
    {
        Issue::whereKey($this->issue->getKey())
            ->update(['summary_status' => SummaryStatus::Failed->value]);
    }
}
