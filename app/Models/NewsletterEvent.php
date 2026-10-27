<?php

namespace App\Models;

use App\NewsletterEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsletterEvent extends Model
{
    protected $fillable = [
        'newsletter_send_id',
        'type',
        'occurred_at',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'type' => NewsletterEventType::class,
            'occurred_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function send(): BelongsTo
    {
        return $this->belongsTo(NewsletterSend::class, 'newsletter_send_id');
    }
}
