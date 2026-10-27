<?php

namespace App\Models;

use App\NewsletterBlockType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsletterBlock extends Model
{
    protected $fillable = [
        'created_by',
        'updated_by',
        'key',
        'name',
        'type',
        'content',
        'is_system',
        'is_active',
    ];

    protected $attributes = [
        'is_system' => false,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'type' => NewsletterBlockType::class,
            'content' => 'array',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
