<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Which account an old forum member id became, kept so the old member links still lead somewhere.
 */
class LegacyImportUserMap extends Model
{
    protected $fillable = [
        'legacy_user_id',
        'user_id',
        'legacy_username',
        'legacy_email',
        'legacy_primary_group_id',
        'legacy_member_group_ids',
        'legacy_is_banned',
        'legacy_ban_reason',
        'legacy_banned_until',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'legacy_is_banned' => 'boolean',
            'legacy_banned_until' => 'datetime',
            'imported_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
