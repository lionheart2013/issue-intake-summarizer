<?php

namespace Tests\Feature;

use App\Enums\Category;
use App\Enums\Priority;
use App\Enums\SummaryStatus;
use App\Jobs\GenerateIssueSummary;
use App\Models\Issue;
use App\Services\Summary\SummaryGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class SummaryJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_job_populates_the_summary_fields(): void
    {
        $issue = Issue::factory()->create([
            'title' => 'Invoice charged twice',
            'description' => 'The customer was charged twice for March. Please refund the duplicate.',
            'priority' => Priority::High,
            'category' => Category::Billing,
        ]);

        (new GenerateIssueSummary($issue))->handle(app(SummaryGenerator::class));

        $issue->refresh();
        $this->assertSame(SummaryStatus::Ready, $issue->summary_status);
        $this->assertNotEmpty($issue->summary);
        $this->assertNotEmpty($issue->suggested_next_action);
    }

    public function test_a_permanently_failed_job_marks_the_summary_as_failed(): void
    {
        $issue = Issue::factory()->create();

        (new GenerateIssueSummary($issue))->failed(new RuntimeException('generator unavailable'));

        $this->assertSame(SummaryStatus::Failed, $issue->fresh()->summary_status);
    }

    public function test_a_created_issue_is_summarised_once_the_queue_runs(): void
    {
        $this->postJson('/issues', [
            'title' => 'Checkout is down',
            'description' => 'Customers cannot pay. Every checkout attempt fails.',
            'priority' => 'high',
            'category' => 'technical',
        ])->assertCreated()->assertJsonPath('data.summary_status', 'pending');

        $issue = Issue::firstOrFail();
        $this->assertSame(SummaryStatus::Ready, $issue->summary_status);
    }
}
