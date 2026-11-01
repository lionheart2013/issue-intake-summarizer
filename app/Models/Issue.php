<?php

namespace App\Models;

use App\Enums\Category;
use App\Enums\Priority;
use App\Enums\Status;
use App\Enums\SummaryStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Issue extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'description', 'priority', 'category', 'status'];

    protected $attributes = [
        'status' => 'open',
        'summary_status' => 'pending',
        'needs_attention' => false,
    ];

    protected static function booted(): void
    {
        // Recomputed on every save, so it can never drift from priority/status.
        static::saving(function (Issue $issue): void {
            $issue->needs_attention = $issue->priority === Priority::High
                && $issue->status !== Status::Resolved;
        });
    }

    protected function casts(): array
    {
        return [
            'priority' => Priority::class,
            'category' => Category::class,
            'status' => Status::class,
            'summary_status' => SummaryStatus::class,
            'needs_attention' => 'boolean',
        ];
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->oldest('id');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['status'] ?? null, fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($filters['priority'] ?? null, fn (Builder $q, string $priority) => $q->where('priority', $priority))
            ->when($filters['category'] ?? null, fn (Builder $q, string $category) => $q->where('category', $category));
    }
}
