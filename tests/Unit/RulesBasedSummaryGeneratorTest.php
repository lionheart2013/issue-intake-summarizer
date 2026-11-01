<?php

namespace Tests\Unit;

use App\Enums\Category;
use App\Enums\Priority;
use App\Models\Issue;
use App\Services\Summary\RulesBasedSummaryGenerator;
use Tests\TestCase;

class RulesBasedSummaryGeneratorTest extends TestCase
{
    private function issue(string $title, string $description, Priority $priority, Category $category): Issue
    {
        return new Issue([
            'title' => $title,
            'description' => $description,
            'priority' => $priority,
            'category' => $category,
        ]);
    }

    public function test_it_writes_a_two_sentence_summary_and_escalates_outages(): void
    {
        $result = (new RulesBasedSummaryGenerator)->generate(
            $this->issue('Checkout is down', 'Customers cannot pay. Every attempt fails.', Priority::High, Category::Technical),
        );

        $this->assertSame('High-priority technical issue: Checkout is down. Customers cannot pay.', $result->summary);
        $this->assertStringContainsString('on-call engineer', $result->suggestedNextAction);
    }

    public function test_it_picks_the_action_from_keywords(): void
    {
        $result = (new RulesBasedSummaryGenerator)->generate(
            $this->issue('Refund request', 'Please refund my last invoice', Priority::Medium, Category::General),
        );

        $this->assertStringContainsString('billing system', $result->suggestedNextAction);
        $this->assertStringEndsWith('.', $result->summary);
    }

    public function test_it_falls_back_to_the_category_default(): void
    {
        $result = (new RulesBasedSummaryGenerator)->generate(
            $this->issue('Newsletter preferences', 'Please change how often I get emails.', Priority::Low, Category::General),
        );

        $this->assertStringContainsString('Assign an owner', $result->suggestedNextAction);
    }
}
