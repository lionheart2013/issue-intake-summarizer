<?php

namespace App\Http\Requests;

use App\Enums\Category;
use App\Enums\Priority;
use App\Enums\Status;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIssueRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string', 'max:10000'],
            'priority' => ['sometimes', 'required', Rule::enum(Priority::class)],
            'category' => ['sometimes', 'required', Rule::enum(Category::class)],
            'status' => ['sometimes', 'required', Rule::enum(Status::class)],
        ];
    }
}
