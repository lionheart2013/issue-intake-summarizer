<?php

namespace App\Http\Controllers;

use App\Enums\SummaryStatus;
use App\Http\Requests\IndexIssueRequest;
use App\Http\Requests\StoreIssueRequest;
use App\Http\Requests\UpdateIssueRequest;
use App\Http\Resources\IssueResource;
use App\Jobs\GenerateIssueSummary;
use App\Models\Issue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class IssueController extends Controller
{
    public function index(IndexIssueRequest $request): AnonymousResourceCollection
    {
        $issues = Issue::query()
            ->filter($request->validated())
            ->withCount('comments')
            ->latest('id')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return IssueResource::collection($issues);
    }

    public function store(StoreIssueRequest $request): JsonResponse
    {
        $issue = Issue::create($request->validated());

        GenerateIssueSummary::dispatch($issue);

        return IssueResource::make($issue)->response()->setStatusCode(201);
    }

    public function show(Issue $issue): IssueResource
    {
        return IssueResource::make($issue->load('comments'));
    }

    public function update(UpdateIssueRequest $request, Issue $issue): IssueResource
    {
        $issue->fill($request->validated());

        $descriptionChanged = $issue->isDirty('description');

        if ($descriptionChanged) {
            $issue->summary_status = SummaryStatus::Pending;
        }

        $issue->save();

        if ($descriptionChanged) {
            GenerateIssueSummary::dispatch($issue);
        }

        return IssueResource::make($issue);
    }
}
