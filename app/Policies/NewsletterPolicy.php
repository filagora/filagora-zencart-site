<?php

namespace App\Policies;

use App\Models\Newsletter;
use App\Policies\Concerns\AuthorizesWithEntitlements;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class NewsletterPolicy
{
    use AuthorizesWithEntitlements, HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $this->allows($authUser, 'operator.newsletter.manage');
    }

    public function view(AuthUser $authUser, Newsletter $newsletter): bool
    {
        return $this->allows($authUser, 'operator.newsletter.manage');
    }

    public function create(AuthUser $authUser): bool
    {
        return $this->allows($authUser, 'operator.newsletter.manage');
    }

    public function update(AuthUser $authUser, Newsletter $newsletter): bool
    {
        return $this->allows($authUser, 'operator.newsletter.manage')
            && ($newsletter->status?->isEditable() ?? false);
    }

    public function delete(AuthUser $authUser, Newsletter $newsletter): bool
    {
        return $this->allows($authUser, 'operator.newsletter.manage')
            && $newsletter->isDeletable();
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function restore(AuthUser $authUser, Newsletter $newsletter): bool
    {
        return false;
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function forceDelete(AuthUser $authUser, Newsletter $newsletter): bool
    {
        return false;
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function replicate(AuthUser $authUser, Newsletter $newsletter): bool
    {
        return false;
    }

    public function reorder(AuthUser $authUser): bool
    {
        return false;
    }
}
