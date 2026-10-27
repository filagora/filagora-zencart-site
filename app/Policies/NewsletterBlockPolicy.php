<?php

namespace App\Policies;

use App\Models\NewsletterBlock;
use App\Policies\Concerns\AuthorizesWithEntitlements;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class NewsletterBlockPolicy
{
    use AuthorizesWithEntitlements, HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $this->allows($authUser, 'operator.newsletter.manage');
    }

    public function view(AuthUser $authUser, NewsletterBlock $newsletterBlock): bool
    {
        return $this->allows($authUser, 'operator.newsletter.manage');
    }

    public function create(AuthUser $authUser): bool
    {
        return $this->allows($authUser, 'operator.newsletter.manage');
    }

    public function update(AuthUser $authUser, NewsletterBlock $newsletterBlock): bool
    {
        return $this->allows($authUser, 'operator.newsletter.manage');
    }

    public function delete(AuthUser $authUser, NewsletterBlock $newsletterBlock): bool
    {
        return $this->allows($authUser, 'operator.newsletter.manage') && ! $newsletterBlock->is_system;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function restore(AuthUser $authUser, NewsletterBlock $newsletterBlock): bool
    {
        return false;
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function forceDelete(AuthUser $authUser, NewsletterBlock $newsletterBlock): bool
    {
        return false;
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function replicate(AuthUser $authUser, NewsletterBlock $newsletterBlock): bool
    {
        return false;
    }

    public function reorder(AuthUser $authUser): bool
    {
        return false;
    }
}
