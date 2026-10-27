<?php

namespace App\Policies;

use App\Models\NewsletterSubscriber;
use App\Policies\Concerns\AuthorizesWithEntitlements;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class NewsletterSubscriberPolicy
{
    use AuthorizesWithEntitlements, HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $this->allows($authUser, 'operator.newsletter.manage');
    }

    public function view(AuthUser $authUser, NewsletterSubscriber $newsletterSubscriber): bool
    {
        return $this->allows($authUser, 'operator.newsletter.manage');
    }

    public function create(AuthUser $authUser): bool
    {
        return $this->allows($authUser, 'operator.newsletter.manage');
    }

    public function update(AuthUser $authUser, NewsletterSubscriber $newsletterSubscriber): bool
    {
        return $this->allows($authUser, 'operator.newsletter.manage');
    }

    public function delete(AuthUser $authUser, NewsletterSubscriber $newsletterSubscriber): bool
    {
        return false;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function restore(AuthUser $authUser, NewsletterSubscriber $newsletterSubscriber): bool
    {
        return false;
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function forceDelete(AuthUser $authUser, NewsletterSubscriber $newsletterSubscriber): bool
    {
        return false;
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function replicate(AuthUser $authUser, NewsletterSubscriber $newsletterSubscriber): bool
    {
        return false;
    }

    public function reorder(AuthUser $authUser): bool
    {
        return false;
    }
}
