<?php

namespace Tests\Feature;

use App\Models\Issue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_adds_a_comment_to_an_issue(): void
    {
        $issue = Issue::factory()->create();

        $this->postJson("/issues/{$issue->id}/comments", [
            'author_name' => ' Dana ',
            'body' => 'Looking into it.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.author_name', 'Dana')
            ->assertJsonPath('data.issue_id', $issue->id);

        $this->assertDatabaseHas('comments', ['issue_id' => $issue->id, 'body' => 'Looking into it.']);
    }

    public function test_it_rejects_empty_comment_fields(): void
    {
        $issue = Issue::factory()->create();

        $this->postJson("/issues/{$issue->id}/comments", ['author_name' => '', 'body' => '   '])
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['details' => ['author_name', 'body']]]);

        $this->assertDatabaseCount('comments', 0);
    }

    public function test_commenting_on_a_missing_issue_returns_404(): void
    {
        $this->postJson('/issues/999/comments', ['author_name' => 'Dana', 'body' => 'Hello'])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'not_found');
    }
}
