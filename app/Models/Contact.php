<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * A member another member keeps in their personal contact list.
 */
class Contact extends Model
{
    protected $fillable = ['user_id', 'contact_user_id'];

    protected static function booted(): void
    {
        static::saving(function (self $contact): void {
            if ((int) $contact->user_id === (int) $contact->contact_user_id) {
                throw new InvalidArgumentException('A user cannot add themselves as a contact.');
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contactUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contact_user_id');
    }
}
