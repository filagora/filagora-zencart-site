<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class NotificationBellDropdownTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_the_bell_lists_only_the_configured_number_of_newest_notifications(): void
    {
        config(['notifications.bell_dropdown_limit' => 3]);
        $user = $this->userWithNotifications(5);

        $html = $this->actingAs($user)->get('/about')->assertOk()->getContent();

        foreach ([5, 4, 3] as $newest) {
            $this->assertStringContainsString("Bell entry {$newest}", $html);
        }
        foreach ([2, 1] as $older) {
            $this->assertStringNotContainsString("Bell entry {$older}", $html);
        }
    }

    public function test_the_default_limit_is_five(): void
    {
        $user = $this->userWithNotifications(8);

        $html = $this->actingAs($user)->get('/about')->assertOk()->getContent();

        $this->assertStringContainsString('Bell entry 8', $html);
        $this->assertStringContainsString('Bell entry 4', $html);
        $this->assertStringNotContainsString('Bell entry 3', $html);
    }

    public function test_view_all_is_linked_at_the_top_of_the_dropdown_not_the_bottom(): void
    {
        $user = $this->userWithNotifications(3);

        $html = $this->actingAs($user)->get('/about')->assertOk()->getContent();

        $viewAll = '<a href="'.route('notifications.index').'"';
        $bell = substr($html, (int) strpos($html, 'data-notif-menu'));
        $bell = substr($bell, 0, (int) strpos($bell, '</details>'));

        $this->assertSame(1, substr_count($bell, $viewAll), 'One link to the full list, inside the bell.');
        $this->assertLessThan(strpos($bell, 'Bell entry 3'), strpos($bell, $viewAll), 'The link comes before the first entry.');
        $this->assertStringContainsString('View all notifications', $bell);
    }

    public function test_the_view_all_link_is_shown_even_when_there_are_no_notifications(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/about')
            ->assertOk()
            ->assertSee('No notifications yet.')
            ->assertSee('View all notifications');
    }

    public function test_the_bell_and_the_full_page_offer_clear_all_only_when_there_is_something_to_clear(): void
    {
        $user = $this->userWithNotifications(2);

        $html = $this->actingAs($user)->get('/about')->assertOk()->getContent();
        $bell = substr($html, (int) strpos($html, 'data-notif-menu'));
        $bell = substr($bell, 0, (int) strpos($bell, '</details>'));

        $this->assertStringContainsString('action="'.route('notifications.clear-all').'"', $bell);
        $this->assertStringContainsString('Clear all', $bell);
        $this->assertStringContainsString('cannot be undone', $bell, 'Clearing asks for confirmation first.');

        $this->actingAs($user)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('action="'.route('notifications.clear-all').'"', false);

        $empty = User::factory()->create();
        $this->actingAs($empty)->get('/about')->assertOk()->assertDontSee(route('notifications.clear-all'), false);
        $this->actingAs($empty)->get(route('notifications.index'))->assertOk()->assertDontSee(route('notifications.clear-all'), false);
    }

    private function userWithNotifications(int $count): User
    {
        $user = User::factory()->create();

        for ($i = 1; $i <= $count; $i++) {
            $user->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => 'test',
                'data' => ['title' => "Bell entry {$i}", 'body' => 'Body', 'url' => '/forums', 'category' => 'forum.mention'],
                'created_at' => now()->subMinutes($count - $i),
            ]);
        }

        return $user;
    }
}
