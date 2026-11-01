<?php

namespace App\Http\Requests;

use App\Enums\Category;
use App\Enums\Priority;
use App\Enums\Status;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIssueRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'priority' => ['required', Rule::enum(Priority::class)],
            'category' => ['required', Rule::enum(Category::class)],
            'status' => ['sometimes', Rule::enum(Status::class)],
        ];
    }
}
