<?php

namespace App\Http\Requests;

use App\Enums\Category;
use App\Enums\Priority;
use App\Enums\Status;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexIssueRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(Status::class)],
            'priority' => ['nullable', Rule::enum(Priority::class)],
            'category' => ['nullable', Rule::enum(Category::class)],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
