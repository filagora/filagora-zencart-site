<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filagora\Entitlements\Concerns\HasEntitlements;
use Filagora\Memberships\Concerns\HasMemberships;
use Filagora\Notifications\Concerns\HasNotificationPreferences;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasEntitlements, HasFactory, HasMemberships, HasNotificationPreferences, Notifiable;

    /**
     * The dashboard is for staff: the site's `operator.dashboard.access` entitlement opens the
     * door, and the individual `operator.*` entitlements decide what is inside it.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasEntitlement('operator.dashboard.access');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
