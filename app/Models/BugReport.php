<?php

namespace App\Models;

use App\BugReportStatus;
use Filagora\Forums\Models\Thread;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BugReport extends Model
{
    protected $fillable = [
        'thread_id',
        'reported_by_id',
        'assigned_to_id',
        'status',
        'description',
        'resolution_note',
        'confirmed_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => BugReportStatus::class,
            'confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }
}
