<?php

namespace App\Models;

use App\NewsletterSubscriberStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewsletterSubscriber extends Model
{
    protected $fillable = [
        'user_id',
        'email',
        'normalized_email',
        'suppression_hash',
        'name',
        'status',
        'source',
        'mailchimp_id',
        'subscribed_at',
        'confirmed_at',
        'unsubscribed_at',
        'bounced_at',
        'complained_at',
        'unsubscribe_reason',
        'consent_text',
        'consent_version',
        'consent_ip',
        'consent_user_agent',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => NewsletterSubscriberStatus::class,
            'subscribed_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
            'bounced_at' => 'datetime',
            'complained_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function newsletterSends(): HasMany
    {
        return $this->hasMany(NewsletterSend::class);
    }

    public function scopeSendable(Builder $query): Builder
    {
        return $query->where('status', NewsletterSubscriberStatus::Subscribed->value);
    }

    public function isSendable(): bool
    {
        return $this->status?->isSendable() ?? false;
    }
}
