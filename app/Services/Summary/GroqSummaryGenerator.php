<?php

namespace App\Services\Summary;

use App\Models\Issue;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class GroqSummaryGenerator implements SummaryGenerator
{
    public function __construct(
        private readonly SummaryPrompt $prompt,
        private readonly ?string $apiKey,
        private readonly string $model,
        private readonly string $baseUrl,
        private readonly int $timeout,
    ) {}

    public function generate(Issue $issue): IssueSummary
    {
        if ($this->apiKey === null || $this->apiKey === '') {
            throw new SummaryGenerationException('GROQ_API_KEY is not configured.');
        }

        try {
            $response = Http::baseUrl($this->baseUrl)
                ->withToken($this->apiKey)
                ->timeout($this->timeout)
                ->acceptJson()
                ->asJson()
                ->post('responses', [
                    'model' => $this->model,
                    'input' => $this->prompt->render($issue),
                ]);
        } catch (ConnectionException $e) {
            throw new SummaryGenerationException('Groq request failed: '.$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            throw new SummaryGenerationException(
                "Groq returned HTTP {$response->status()}: ".Str::limit($response->body(), 300)
            );
        }

        return $this->parse($response->json());
    }

    private function parse(mixed $body): IssueSummary
    {
        // The reply lists several items; reasoning models add "reasoning" items before the "message" with the answer.
        $text = collect(data_get($body, 'output', []))
            ->where('type', 'message')
            ->flatMap(fn ($item) => data_get($item, 'content', []))
            ->where('type', 'output_text')
            ->pluck('text')
            ->implode('');

        // Models sometimes wrap the JSON in a code fence or add a sentence around it.
        preg_match('/\{.*\}/s', $text, $match);
        $data = json_decode($match[0] ?? '', true);

        $summary = is_array($data) ? trim((string) ($data['summary'] ?? '')) : '';
        $action = is_array($data) ? trim((string) ($data['suggested_next_action'] ?? '')) : '';

        if ($summary === '' || $action === '') {
            throw new SummaryGenerationException('Groq returned a response without a summary and next action.');
        }

        return new IssueSummary(
            summary: Str::limit($summary, 500),
            suggestedNextAction: Str::limit($action, 300),
        );
    }
}
