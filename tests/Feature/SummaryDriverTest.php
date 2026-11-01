<?php

namespace Tests\Feature;

use App\Enums\Category;
use App\Enums\Priority;
use App\Enums\SummaryStatus;
use App\Jobs\GenerateIssueSummary;
use App\Models\Issue;
use App\Services\Summary\RulesBasedSummaryGenerator;
use App\Services\Summary\SummaryGenerator;
use App\Services\Summary\SummaryPrompt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class SummaryDriverTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = 'api.groq.com/*';

    /**
     * A reply shaped like Groq's: a reasoning item first, then the message holding the answer.
     *
     * @return array<string, mixed>
     */
    private function groqReply(string $text): array
    {
        return ['output' => [
            ['type' => 'reasoning', 'content' => [[
                'type' => 'reasoning_text',
                'text' => '{"summary":"from reasoning","suggested_next_action":"ignore me"}',
            ]]],
            ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => $text]]],
        ]];
    }

    private function useGroq(?string $apiKey = 'test-key'): void
    {
        config(['summary.driver' => 'groq', 'summary.groq.api_key' => $apiKey]);
    }

    private function assertFallsBackToRules(Issue $issue): void
    {
        $expected = (new RulesBasedSummaryGenerator)->generate($issue);

        $this->assertEquals($expected, app(SummaryGenerator::class)->generate($issue));
    }

    public function test_the_groq_driver_returns_the_llm_output_and_sends_the_prompt(): void
    {
        $this->useGroq();
        Http::fake([self::ENDPOINT => Http::response($this->groqReply(json_encode([
            'summary' => 'Customers cannot complete checkout.',
            'suggested_next_action' => 'Escalate to the on-call engineer.',
        ])))]);
        $issue = Issue::factory()->create(['title' => 'Checkout is down']);

        $result = app(SummaryGenerator::class)->generate($issue);

        $this->assertSame('Customers cannot complete checkout.', $result->summary);
        $this->assertSame('Escalate to the on-call engineer.', $result->suggestedNextAction);
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer test-key')
            && str_ends_with($request->url(), '/responses')
            && $request['model'] === config('summary.groq.model')
            && str_contains($request['input'], 'Checkout is down'));
    }

    public function test_it_extracts_the_json_from_a_reply_with_surrounding_text(): void
    {
        $this->useGroq();
        $json = json_encode(['summary' => 'Payments fail.', 'suggested_next_action' => 'Check the gateway.']);
        Http::fake([self::ENDPOINT => Http::response($this->groqReply("Here you go:\n```json\n{$json}\n```"))]);

        $result = app(SummaryGenerator::class)->generate(Issue::factory()->create());

        $this->assertSame('Payments fail.', $result->summary);
        $this->assertSame('Check the gateway.', $result->suggestedNextAction);
    }

    public function test_it_falls_back_to_rules_when_no_api_key_is_set(): void
    {
        $this->useGroq(null);
        Http::fake();

        $this->assertFallsBackToRules(Issue::factory()->create());

        Http::assertNothingSent();
    }

    public function test_it_falls_back_to_rules_when_the_api_returns_an_error(): void
    {
        $this->useGroq();
        Http::fake([self::ENDPOINT => Http::response('', 500)]);

        $this->assertFallsBackToRules(Issue::factory()->create());
    }

    public function test_it_falls_back_to_rules_when_the_request_times_out(): void
    {
        $this->useGroq();
        Http::fake(fn () => throw new ConnectionException('Connection timed out'));

        $this->assertFallsBackToRules(Issue::factory()->create());
    }

    public function test_it_falls_back_to_rules_when_the_response_is_not_the_expected_json(): void
    {
        $this->useGroq();
        Http::fake([self::ENDPOINT => Http::response($this->groqReply('Sorry, I cannot help with that.'))]);

        $this->assertFallsBackToRules(Issue::factory()->create());
    }

    public function test_the_job_still_completes_when_the_llm_is_down(): void
    {
        $this->useGroq();
        Http::fake([self::ENDPOINT => Http::response('', 503)]);
        $issue = Issue::factory()->create();

        (new GenerateIssueSummary($issue))->handle(app(SummaryGenerator::class));

        $issue->refresh();
        $this->assertSame(SummaryStatus::Ready, $issue->summary_status);
        $this->assertNotEmpty($issue->summary);
    }

    public function test_the_rules_driver_never_calls_the_api(): void
    {
        Http::fake();

        $generator = app(SummaryGenerator::class);
        $generator->generate(Issue::factory()->create());

        $this->assertInstanceOf(RulesBasedSummaryGenerator::class, $generator);
        Http::assertNothingSent();
    }

    public function test_an_unknown_driver_is_rejected(): void
    {
        config(['summary.driver' => 'openai']);

        $this->expectException(InvalidArgumentException::class);

        app(SummaryGenerator::class);
    }

    public function test_the_prompt_template_is_filled_in_without_recursive_substitution(): void
    {
        $issue = new Issue([
            'title' => 'Login {{description}} fails',
            'description' => 'Cannot sign in.',
            'priority' => Priority::High,
            'category' => Category::Account,
        ]);

        $prompt = (new SummaryPrompt(config('summary.prompt_path')))->render($issue);

        $this->assertStringContainsString('Title: Login {{description}} fails', $prompt);
        $this->assertStringContainsString('Priority: high', $prompt);
        $this->assertStringContainsString('Cannot sign in.', $prompt);
    }
}
