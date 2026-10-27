<?php

namespace App\Models;

use App\NewsletterStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Newsletter extends Model
{
    protected $fillable = [
        'created_by',
        'updated_by',
        'title',
        'subject',
        'preheader',
        'background_color',
        'inner_background_color',
        'body_html',
        'body_text',
        'content_blocks',
        'status',
        'scheduled_at',
        'sent_at',
        'metadata',
    ];

    protected $attributes = [
        'status' => 'draft',
        'background_color' => '#F4F1EA',
        'inner_background_color' => '#FBF8F2',
    ];

    protected function casts(): array
    {
        return [
            'status' => NewsletterStatus::class,
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'content_blocks' => 'array',
            'metadata' => 'array',
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

    public function sends(): HasMany
    {
        return $this->hasMany(NewsletterSend::class);
    }

    public function linkClicks(): HasMany
    {
        return $this->hasMany(NewsletterLinkClick::class);
    }

    /**
     * A newsletter is only safe to delete before it has ever been queued for sending —
     * deleting cascades to `newsletter_sends`/`newsletter_events`/`newsletter_link_clicks`,
     * which would destroy real delivery/audit history for a campaign that actually went out.
     */
    public function isDeletable(): bool
    {
        return ($this->status?->isEditable() ?? false) && ! $this->sends()->exists();
    }
}
