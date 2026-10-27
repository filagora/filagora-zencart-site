<?php

namespace App\Models;

use App\NewsletterSendStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewsletterSend extends Model
{
    protected $fillable = [
        'newsletter_id',
        'newsletter_subscriber_id',
        'email',
        'normalized_email',
        'name',
        'status',
        'queued_at',
        'dispatched_at',
        'sent_at',
        'failed_at',
        'skipped_at',
        'provider_message_id',
        'error',
        'metadata',
    ];

    protected $attributes = [
        'status' => 'queued',
    ];

    protected function casts(): array
    {
        return [
            'status' => NewsletterSendStatus::class,
            'queued_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
            'skipped_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function newsletter(): BelongsTo
    {
        return $this->belongsTo(Newsletter::class);
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(NewsletterSubscriber::class, 'newsletter_subscriber_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(NewsletterEvent::class);
    }

    public function linkClicks(): HasMany
    {
        return $this->hasMany(NewsletterLinkClick::class);
    }
}
