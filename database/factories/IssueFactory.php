<?php

namespace Database\Factories;

use App\Enums\Category;
use App\Enums\Priority;
use App\Enums\Status;
use App\Enums\SummaryStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class IssueFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'priority' => fake()->randomElement(Priority::cases()),
            'category' => fake()->randomElement(Category::cases()),
            'status' => Status::Open,
        ];
    }

    public function withSummary(): static
    {
        return $this->state([
            'summary' => 'Existing summary.',
            'suggested_next_action' => 'Existing next action.',
            'summary_status' => SummaryStatus::Ready,
        ]);
    }
}
