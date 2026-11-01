<?php

namespace App\Providers;

use App\Services\Summary\FallbackSummaryGenerator;
use App\Services\Summary\GroqSummaryGenerator;
use App\Services\Summary\RulesBasedSummaryGenerator;
use App\Services\Summary\SummaryGenerator;
use App\Services\Summary\SummaryPrompt;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SummaryGenerator::class, fn (): SummaryGenerator => match (config('summary.driver')) {
            'rules' => new RulesBasedSummaryGenerator,
            'groq' => new FallbackSummaryGenerator(
                primary: new GroqSummaryGenerator(
                    prompt: new SummaryPrompt(config('summary.prompt_path')),
                    apiKey: config('summary.groq.api_key'),
                    model: config('summary.groq.model'),
                    baseUrl: config('summary.groq.base_url'),
                    timeout: config('summary.groq.timeout'),
                ),
                fallback: new RulesBasedSummaryGenerator,
            ),
            default => throw new InvalidArgumentException(
                'Unknown SUMMARY_DRIVER "'.config('summary.driver').'". Use "rules" or "groq".'
            ),
        });
    }
}
