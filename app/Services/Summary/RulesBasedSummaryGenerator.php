<?php

namespace App\Services\Summary;

use App\Enums\Category;
use App\Models\Issue;
use Illuminate\Support\Str;

final class RulesBasedSummaryGenerator implements SummaryGenerator
{
    /**
     * Checked in order against the title and description; the first match wins.
     * Each entry maps a next action to the keywords that trigger it.
     */
    private const KEYWORD_ACTIONS = [
        'Escalate to the on-call engineer and confirm how many users are affected.' => [
            'outage', 'down', 'crash', 'unreachable', 'data loss', 'security breach',
        ],
        'Check the transaction in the billing system and reply to the customer with the outcome.' => [
            'refund', 'charged', 'charge', 'invoice', 'payment',
        ],
        'Verify the requester identity and send a secure access-recovery link.' => [
            'password', 'login', 'log in', 'sign in', 'locked out', 'two-factor', '2fa',
        ],
        'Reproduce the problem and attach the steps and logs to the ticket.' => [
            'error', 'bug', 'exception', 'fails', 'failing', 'broken',
        ],
    ];

    public function generate(Issue $issue): IssueSummary
    {
        return new IssueSummary(
            summary: $this->summarise($issue),
            suggestedNextAction: $this->nextAction($issue),
        );
    }

    private function summarise(Issue $issue): string
    {
        return sprintf(
            '%s-priority %s issue: %s. %s',
            ucfirst($issue->priority->value),
            $issue->category->value,
            rtrim($issue->title, '.!?: '),
            $this->firstSentence($issue->description),
        );
    }

    private function firstSentence(string $text): string
    {
        $sentence = preg_split('/(?<=[.!?])\s+/', Str::squish($text), 2)[0];
        $sentence = Str::limit($sentence, 160);

        return preg_match('/[.!?]$/', $sentence) ? $sentence : $sentence.'.';
    }

    private function nextAction(Issue $issue): string
    {
        $text = Str::lower($issue->title.' '.$issue->description);

        foreach (self::KEYWORD_ACTIONS as $action => $keywords) {
            $pattern = '/\b(?:'.implode('|', array_map(fn (string $keyword) => preg_quote($keyword, '/'), $keywords)).')\b/';

            if (preg_match($pattern, $text)) {
                return $action;
            }
        }

        return match ($issue->category) {
            Category::Billing => 'Review the billing history and confirm the correct amount with the customer.',
            Category::Technical => 'Reproduce the problem and attach the steps and logs to the ticket.',
            Category::Account => 'Verify the requester identity, then review the account settings.',
            Category::General => 'Assign an owner and reply to the requester to acknowledge the request.',
        };
    }
}
