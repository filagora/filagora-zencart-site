<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class NotificationBellTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    private function pushDatabaseNotification(User $user, string $title = 'A reply', string $url = '/threads/1'): void
    {
        $user->notify(new class($title, $url) extends Notification
        {
            public function __construct(private string $title, private string $url) {}

            public function via(object $notifiable): array
            {
                return ['database'];
            }

            /** @return array<string, string> */
            public function toArray(object $notifiable): array
            {
                return ['title' => $this->title, 'body' => 'body text', 'url' => $this->url, 'category' => 'forum.thread_updates_instant'];
            }
        });
    }

    public function test_header_shows_the_bell_with_an_unread_count(): void
    {
        $user = User::factory()->create();
        $this->pushDatabaseNotification($user, 'First');
        $this->pushDatabaseNotification($user, 'Second');

        $response = $this->actingAs($user)->get('/forums');

        $response->assertOk();
        $response->assertSee('fa-regular fa-bell', false);
        $response->assertSee('aria-label="2 unread notifications"', false);
        $response->assertSee('First');
    }

    public function test_notifications_index_lists_the_users_notifications(): void
    {
        $user = User::factory()->create();
        $this->pushDatabaseNotification($user, 'Listed notification');

        $this->actingAs($user)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Listed notification');
    }

    public function test_opening_a_notification_marks_it_read_and_redirects_to_its_target(): void
    {
        $user = User::factory()->create();
        $this->pushDatabaseNotification($user, 'Go there', '/threads/42#post-9');
        $notification = $user->notifications()->firstOrFail();

        $this->actingAs($user)->get(route('notifications.open', $notification->id))
            ->assertRedirect('/threads/42#post-9');

        $this->assertNotNull($user->notifications()->firstOrFail()->read_at);
    }

    public function test_opening_a_notification_rewrites_a_same_host_absolute_url_to_its_path(): void
    {
        config(['app.url' => 'https://forum.test']);
        $user = User::factory()->create();
        $this->pushDatabaseNotification($user, 'Abs', 'https://forum.test/threads/7#post-3');
        $notification = $user->notifications()->firstOrFail();

        $this->actingAs($user)->get(route('notifications.open', $notification->id))
            ->assertRedirect('/threads/7#post-3');
    }

    public function test_opening_a_notification_refuses_an_off_site_target(): void
    {
        config(['app.url' => 'https://forum.test']);
        $user = User::factory()->create();

        foreach ([
            '//evil.example/x',
            'https://evil.example/x',
            'https://forum.test.evil.example/x',
            'https:evil.example',
            '/\\evil.example',
        ] as $bad) {
            $this->pushDatabaseNotification($user, 'Bad', $bad);
            $notification = $user->notifications()->latest()->firstOrFail();

            $this->actingAs($user)->get(route('notifications.open', $notification->id))
                ->assertRedirect(route('notifications.index'));
        }
    }

    public function test_opening_a_notification_that_is_not_yours_is_a_404(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->pushDatabaseNotification($owner);
        $notification = $owner->notifications()->firstOrFail();

        $this->actingAs($other)->get(route('notifications.open', $notification->id))
            ->assertNotFound();
    }

    public function test_unread_filter_lists_only_unread_notifications(): void
    {
        $user = User::factory()->create();
        $this->pushDatabaseNotification($user, 'Pending item');
        $this->pushDatabaseNotification($user, 'Handled item');
        $user->notifications()->where('data->title', 'Handled item')->firstOrFail()->markAsRead();

        // assertSee/assertDontSee would also match the header bell dropdown,
        // which always shows the latest few regardless of read state - assert
        // against the page's own paginator instead.
        $unreadTitles = $this->actingAs($user)->get(route('notifications.index', ['filter' => 'unread']))
            ->assertOk()
            ->viewData('notifications')
            ->pluck('data.title');
        $this->assertSame(['Pending item'], $unreadTitles->all());

        $allTitles = $this->actingAs($user)->get(route('notifications.index'))
            ->assertOk()
            ->viewData('notifications')
            ->pluck('data.title');
        $this->assertEqualsCanonicalizing(['Pending item', 'Handled item'], $allTitles->all());
    }

    public function test_mark_all_read(): void
    {
        $user = User::factory()->create();
        $this->pushDatabaseNotification($user, 'One');
        $this->pushDatabaseNotification($user, 'Two');

        $this->actingAs($user)
            ->from(route('notifications.index'))
            ->post(route('notifications.read-all'))
            ->assertRedirect(route('notifications.index'));

        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_a_user_can_remove_a_single_notification(): void
    {
        $user = User::factory()->create();
        $this->pushDatabaseNotification($user, 'Keep');
        $this->pushDatabaseNotification($user, 'Drop');
        $drop = $user->notifications()->where('data->title', 'Drop')->firstOrFail();

        $this->actingAs($user)
            ->from(route('notifications.index'))
            ->delete(route('notifications.destroy', $drop->id))
            ->assertRedirect();

        $this->assertSame(1, $user->notifications()->count());
        $this->assertNull($user->notifications()->whereKey($drop->id)->first());
    }

    public function test_a_user_cannot_delete_someone_elses_notification(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->pushDatabaseNotification($owner);
        $notification = $owner->notifications()->firstOrFail();

        $this->actingAs($other)->delete(route('notifications.destroy', $notification->id));

        $this->assertSame(1, $owner->notifications()->count());
    }

    public function test_clear_read_removes_only_read_notifications(): void
    {
        $user = User::factory()->create();
        $this->pushDatabaseNotification($user, 'Unread');
        $this->pushDatabaseNotification($user, 'Read');
        $user->notifications()->where('data->title', 'Read')->firstOrFail()->markAsRead();

        $this->actingAs($user)->post(route('notifications.clear-read'))->assertRedirect(route('notifications.index'));

        $this->assertSame(1, $user->notifications()->count());
        $this->assertSame('Unread', $user->notifications()->firstOrFail()->data['title']);
    }

    public function test_clear_all_removes_every_notification_of_the_member_and_nobody_elses(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->pushDatabaseNotification($user, 'Unread');
        $this->pushDatabaseNotification($user, 'Read');
        $user->notifications()->where('data->title', 'Read')->firstOrFail()->markAsRead();
        $this->pushDatabaseNotification($other, 'Someone else');

        $this->actingAs($user)
            ->from(route('notifications.index'))
            ->post(route('notifications.clear-all'))
            ->assertRedirect(route('notifications.index'))
            ->assertSessionHas('profile_status', __('notifications.bell.cleared_all'));

        $this->assertSame(0, $user->notifications()->count());
        $this->assertSame(1, $other->notifications()->count());
    }

    public function test_clear_all_needs_a_signed_in_member(): void
    {
        $user = User::factory()->create();
        $this->pushDatabaseNotification($user, 'Kept');

        $this->post(route('notifications.clear-all'))->assertRedirect();

        $this->assertSame(1, $user->notifications()->count());
    }

    public function test_prune_command_drops_read_notifications_past_the_window_and_ancient_unread(): void
    {
        $user = User::factory()->create();
        $this->pushDatabaseNotification($user, 'fresh');
        $this->pushDatabaseNotification($user, 'old read');
        $this->pushDatabaseNotification($user, 'ancient unread');

        $user->notifications()->where('data->title', 'old read')->update([
            'read_at' => now()->subDays(90),
            'created_at' => now()->subDays(90),
        ]);
        $user->notifications()->where('data->title', 'ancient unread')->update([
            'created_at' => now()->subDays(400),
        ]);

        $this->artisan('notifications:prune')->assertSuccessful();

        $titles = $user->notifications()->pluck('data')->map(fn ($d) => $d['title'])->all();
        $this->assertSame(['fresh'], $titles);
    }

    public function test_guest_cannot_view_notifications(): void
    {
        $this->get(route('notifications.index'))->assertRedirect();
    }
}
