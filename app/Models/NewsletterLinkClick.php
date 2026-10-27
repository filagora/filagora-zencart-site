<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsletterLinkClick extends Model
{
    protected $fillable = [
        'newsletter_id',
        'newsletter_send_id',
        'newsletter_block_id',
        'block_key',
        'label',
        'destination_url',
        'clicked_at',
        'ip_hash',
    ];

    protected function casts(): array
    {
        return [
            'clicked_at' => 'datetime',
        ];
    }

    public function newsletter(): BelongsTo
    {
        return $this->belongsTo(Newsletter::class);
    }

    public function send(): BelongsTo
    {
        return $this->belongsTo(NewsletterSend::class, 'newsletter_send_id');
    }

    public function libraryBlock(): BelongsTo
    {
        return $this->belongsTo(NewsletterBlock::class, 'newsletter_block_id');
    }
}
