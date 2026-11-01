<?php

namespace Tests\Feature;

use App\Enums\Category;
use App\Enums\Priority;
use App\Enums\Status;
use App\Jobs\GenerateIssueSummary;
use App\Models\Comment;
use App\Models\Issue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class IssueApiTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Cannot log in',
            'description' => 'The password reset email never arrives.',
            'priority' => 'high',
            'category' => 'account',
        ], $overrides);
    }

    public function test_it_creates_an_issue_with_defaults(): void
    {
        Queue::fake();

        $this->postJson('/issues', $this->validPayload(['title' => '  Cannot log in  ']))
            ->assertCreated()
            ->assertJsonPath('data.title', 'Cannot log in')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.summary_status', 'pending')
            ->assertJsonPath('data.summary', null)
            ->assertJsonPath('data.needs_attention', true);

        $this->assertDatabaseHas('issues', ['title' => 'Cannot log in', 'priority' => 'high']);
    }

    public function test_it_rejects_missing_and_invalid_fields(): void
    {
        $this->postJson('/issues', ['title' => '   ', 'priority' => 'urgent'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'details' => ['title', 'description', 'priority', 'category'],
                ],
            ]);

        $this->assertDatabaseCount('issues', 0);
    }

    public function test_it_rejects_an_invalid_status(): void
    {
        $this->postJson('/issues', $this->validPayload(['status' => 'closed']))
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['details' => ['status']]]);
    }

    public function test_list_filters_can_be_combined(): void
    {
        $match = Issue::factory()->create([
            'status' => Status::Open,
            'category' => Category::Billing,
            'priority' => Priority::High,
        ]);

        Issue::factory()->create(['status' => Status::Open, 'category' => Category::Billing, 'priority' => Priority::Low]);
        Issue::factory()->create(['status' => Status::Resolved, 'category' => Category::Billing, 'priority' => Priority::High]);
        Issue::factory()->create(['status' => Status::Open, 'category' => Category::Technical, 'priority' => Priority::High]);

        $this->getJson('/issues?status=open&category=billing&priority=high')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);
    }

    public function test_list_rejects_an_invalid_filter_value(): void
    {
        $this->getJson('/issues?priority=urgent')
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['details' => ['priority']]]);
    }

    public function test_list_does_not_run_a_query_per_issue(): void
    {
        Issue::factory()->count(5)->has(Comment::factory()->count(3), 'comments')->create();

        DB::enableQueryLog();
        $response = $this->getJson('/issues');
        $queries = DB::getQueryLog();

        $response->assertOk()->assertJsonCount(5, 'data')->assertJsonPath('data.0.comments_count', 3);
        $this->assertLessThanOrEqual(2, count($queries));
    }

    public function test_show_returns_comments_without_n_plus_one(): void
    {
        $issue = Issue::factory()->has(Comment::factory()->count(5), 'comments')->create();

        DB::enableQueryLog();
        $response = $this->getJson("/issues/{$issue->id}");
        $queries = DB::getQueryLog();

        $response->assertOk()->assertJsonCount(5, 'data.comments');
        $this->assertCount(2, $queries);
    }

    public function test_showing_a_missing_issue_returns_a_json_404(): void
    {
        $this->getJson('/issues/999')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'not_found')
            ->assertJsonStructure(['error' => ['code', 'message', 'details']]);
    }

    public function test_needs_attention_follows_priority_and_status(): void
    {
        $issue = Issue::factory()->create(['priority' => Priority::Low]);
        $this->assertFalse($issue->needs_attention);

        $this->patchJson("/issues/{$issue->id}", ['priority' => 'high'])
            ->assertOk()
            ->assertJsonPath('data.needs_attention', true);

        $this->patchJson("/issues/{$issue->id}", ['status' => 'resolved'])
            ->assertOk()
            ->assertJsonPath('data.needs_attention', false);
    }

    public function test_creating_an_issue_dispatches_the_summary_job(): void
    {
        Queue::fake();

        $this->postJson('/issues', $this->validPayload())->assertCreated();

        Queue::assertPushed(GenerateIssueSummary::class, 1);
    }

    public function test_changing_the_description_regenerates_the_summary_but_changing_status_does_not(): void
    {
        Queue::fake();
        $issue = Issue::factory()->withSummary()->create();

        $this->patchJson("/issues/{$issue->id}", ['status' => 'in_progress'])
            ->assertOk()
            ->assertJsonPath('data.summary_status', 'ready');
        Queue::assertNotPushed(GenerateIssueSummary::class);

        $this->patchJson("/issues/{$issue->id}", ['description' => 'Completely new details.'])
            ->assertOk()
            ->assertJsonPath('data.summary_status', 'pending');
        Queue::assertPushed(GenerateIssueSummary::class, 1);
    }
}
