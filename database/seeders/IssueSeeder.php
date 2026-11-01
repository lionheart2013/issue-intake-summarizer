<?php

namespace Database\Seeders;

use App\Enums\Category;
use App\Enums\Priority;
use App\Enums\Status;
use App\Jobs\GenerateIssueSummary;
use App\Models\Issue;
use Illuminate\Database\Seeder;

class IssueSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->issues() as $data) {
            $comments = $data['comments'];
            unset($data['comments']);

            $issue = Issue::create($data);
            $issue->comments()->createMany($comments);

            // Run inline so seeded issues already have summaries without a worker.
            GenerateIssueSummary::dispatchSync($issue);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function issues(): array
    {
        return [
            [
                'title' => 'Customer charged twice for March invoice',
                'description' => 'A customer reports two identical charges on their March invoice. They attached both receipts and are asking for a refund of the duplicate payment.',
                'priority' => Priority::High,
                'category' => Category::Billing,
                'status' => Status::Open,
                'comments' => [
                    ['author_name' => 'Dana Reyes', 'body' => 'Confirmed the duplicate charge in the billing system.'],
                    ['author_name' => 'Marcus Lee', 'body' => 'Refund requested from the payment provider, waiting for confirmation.'],
                ],
            ],
            [
                'title' => 'Dashboard shows an error after login',
                'description' => 'Several users see a 500 error on the dashboard right after signing in. The problem started after the latest deployment.',
                'priority' => Priority::High,
                'category' => Category::Technical,
                'status' => Status::InProgress,
                'comments' => [
                    ['author_name' => 'Priya Nair', 'body' => 'Reproduced locally, looks related to the new reports widget.'],
                ],
            ],
            [
                'title' => 'Update billing email address',
                'description' => 'Customer asks to change the email address that receives their monthly invoices.',
                'priority' => Priority::Low,
                'category' => Category::Billing,
                'status' => Status::Resolved,
                'comments' => [
                    ['author_name' => 'Dana Reyes', 'body' => 'Address updated and the customer has been notified.'],
                ],
            ],
            [
                'title' => 'Password reset email not arriving',
                'description' => 'A user requested a password reset three times but never received the email. They already checked their spam folder.',
                'priority' => Priority::Medium,
                'category' => Category::Account,
                'status' => Status::Open,
                'comments' => [],
            ],
            [
                'title' => 'Add CSV export to monthly report',
                'description' => 'A customer would like to export the monthly report as a CSV file for their finance team.',
                'priority' => Priority::Low,
                'category' => Category::General,
                'status' => Status::Open,
                'comments' => [
                    ['author_name' => 'Marcus Lee', 'body' => 'Logged as a feature request for the next planning round.'],
                ],
            ],
            [
                'title' => 'Service outage in the EU region',
                'description' => 'The EU region was unreachable for about twenty minutes this morning. A post-incident review is planned once the service is stable.',
                'priority' => Priority::High,
                'category' => Category::Technical,
                'status' => Status::InProgress,
                'comments' => [
                    ['author_name' => 'Priya Nair', 'body' => 'Traffic is recovering, monitoring for another hour.'],
                    ['author_name' => 'Sam Okafor', 'body' => 'Drafting the customer status update now.'],
                ],
            ],
            [
                'title' => 'Two-factor codes are rejected',
                'description' => 'A user reports that valid two-factor codes are rejected when they sign in on a new phone.',
                'priority' => Priority::Medium,
                'category' => Category::Account,
                'status' => Status::InProgress,
                'comments' => [],
            ],
        ];
    }
}
