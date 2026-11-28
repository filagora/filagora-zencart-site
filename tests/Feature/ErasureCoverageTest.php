<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Compliance\Models\PrivacyRequest;
use Filagora\Compliance\Services\PersonalDataExport;
use Filagora\Compliance\Services\PrivacyRequests;
use Filagora\Newsletters\Models\NewsletterSubscriber;
use Filagora\Newsletters\Services\Subscribers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ErasureCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_export_and_erasure_cover_newsletters_profile_text_and_the_old_forum_link(): void
    {
        $member = User::factory()->create(['email_verified_at' => now()]);
        $member->forceFill(['biography' => 'About me', 'location' => 'Leeds', 'interests' => 'Carts', 'occupation' => 'Developer', 'user_title' => 'Regular'])->save();
        DB::table('legacy_import_user_maps')->insert(['legacy_user_id' => 900001, 'user_id' => $member->id, 'legacy_username' => 'old name', 'legacy_email' => 'old-forum@example.test']);

        $subscribers = app(Subscribers::class);
        $first = $member->email;
        $subscribers->subscribeFromProfile($member);
        $member->forceFill(['email' => 'second-address@example.test'])->save();
        $subscribers->unlinkUserForEmailChange($member, $first);
        $subscribers->subscribeFromProfile($member);

        $export = app(PersonalDataExport::class)->build($member);

        $this->assertCount(2, $export['newsletters']['subscriptions']);
        $this->assertSame('old name', $export['old_forum']['accounts'][0]['link']['legacy_username']);

        $viewer = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($viewer)->get(route('members.show', $member))->assertOk()->assertSee('Leeds');

        $request = PrivacyRequest::query()->create(['user_id' => $member->id, 'type' => 'erasure', 'request_details' => 'Please'])->refresh();
        app(PrivacyRequests::class)->fulfilErasure($request, User::factory()->create());

        $this->assertSame(2, NewsletterSubscriber::query()->where('email', 'like', 'erased-subscriber-%')->count());
        $this->assertSame(0, NewsletterSubscriber::query()->sendable()->count());
        $this->assertFalse(DB::table('legacy_import_user_maps')->where('user_id', $member->id)->exists());

        $member->refresh();

        foreach (['biography', 'location', 'interests', 'occupation', 'user_title'] as $column) {
            $this->assertNull($member->getAttribute($column), $column);
        }

        $this->actingAs($viewer)->get(route('members.show', $member))->assertOk()->assertDontSee('Leeds')->assertDontSee('About me');
    }
}
