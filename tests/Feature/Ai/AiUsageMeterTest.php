<?php

namespace Tests\Feature\Ai;

use App\Models\User;
use Carbon\CarbonImmutable;
use Filagora\Ai\Models\AiChatUsage;
use Filagora\Ai\Models\AiEmbeddingUsage;
use Filagora\Ai\Usage\UsageMeterService;
use Filagora\Memberships\Models\MembershipPlan;
use Filagora\Memberships\Models\UserMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The AI assist meter on the Billing tab: who sees it, what it counts and how it reads.
 */
class AiUsageMeterTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->member = User::factory()->create();
    }

    private function usage(User $user, string $context, string $when, bool $succeeded = true, string $basis = 'reported', float $cost = 0.01): void
    {
        AiChatUsage::query()->create([
            'user_id' => $user->id, 'provider' => 'spy', 'model' => 'spy', 'context' => $context,
            'input_tokens' => 10, 'output_tokens' => 5, 'estimated_cost_usd' => $cost, 'cost_basis' => $basis, 'succeeded' => $succeeded,
        ])->forceFill(['created_at' => CarbonImmutable::parse($when)->setTime(12, 0)])->save();
    }

    public function test_the_window_follows_the_members_membership_billing_date(): void
    {
        $plan = MembershipPlan::query()->create(['code' => 'ai', 'name' => 'AI', 'is_active' => true, 'is_free' => false, 'provider' => 'paypal', 'entitlements' => ['ai_chat.access']]);
        UserMembership::query()->create([
            'user_id' => $this->member->id, 'membership_plan_id' => $plan->id, 'status' => 'active', 'provider' => 'paypal',
            'subscription_name' => 'default', 'starts_at' => '2026-10-15 09:30:00', 'current_period_start' => '2026-10-15 09:30:00', 'current_period_end' => '2026-11-15 09:30:00',
        ]);

        [$from] = app(UsageMeterService::class)->window($this->member, CarbonImmutable::parse('2026-11-20 12:00:00'));

        $this->assertSame('2026-11-15 09:30:00', $from->toDateTimeString());
    }

    public function test_the_billing_tab_shows_a_count_of_answers_when_there_is_no_allowance(): void
    {
        config(['filagora-ai.chat.allowance_usd' => null]);
        $this->grantEntitlement($this->member, 'ai_chat.access');
        $this->usage($this->member, 'ai_chat_reply', now()->toDateString());
        $this->usage($this->member, 'thread_assist', now()->toDateString());

        $html = $this->actingAs($this->member)->get(route('profile.show', ['tab' => 'billing']))->assertOk()->getContent();

        $this->assertStringContainsString('data-ai-usage', $html);
        $this->assertStringContainsString('AI assist', $html);
        $this->assertMatchesRegularExpression('#data-ai-usage.*?>\s*2\s*<span[^>]*>answers</span>#s', $html);
        $this->assertStringNotContainsString('role="progressbar"', $html);
        // Never an amount of money: members are not all paying in dollars.
        $this->assertStringNotContainsString('$', substr($html, (int) strpos($html, 'data-ai-usage'), 900));
    }

    public function test_the_billing_tab_shows_a_percentage_bar_when_there_is_an_allowance(): void
    {
        config(['filagora-ai.chat.allowance_usd' => 2.0]);
        $this->grantEntitlement($this->member, 'ai_chat.access');
        $this->usage($this->member, 'ai_chat_reply', now()->toDateString(), cost: 1.0);

        $this->actingAs($this->member)->get(route('profile.show', ['tab' => 'billing']))
            ->assertOk()
            ->assertSee('50%', false)
            ->assertSee('role="progressbar"', false)
            ->assertSee('aria-valuenow="50"', false)
            ->assertSee('width: 50%', false);
    }

    public function test_the_bar_never_overflows_when_the_allowance_is_exceeded(): void
    {
        config(['filagora-ai.chat.allowance_usd' => 1.0]);
        $this->usage($this->member, 'ai_chat_reply', now()->toDateString(), cost: 3.0);

        $this->actingAs($this->member)->get(route('profile.show', ['tab' => 'billing']))
            ->assertOk()
            ->assertSee('300%', false)
            ->assertSee('aria-valuenow="100"', false)
            ->assertSee('width: 100%', false);
    }

    public function test_the_billing_tab_says_nothing_about_ai_to_someone_with_no_access_or_use(): void
    {
        $this->actingAs($this->member)->get(route('profile.show', ['tab' => 'billing']))
            ->assertOk()
            ->assertDontSee('data-ai-usage', false);
    }

    public function test_staff_with_thread_assist_see_it_before_their_first_answer(): void
    {
        $this->grantEntitlement($this->member, 'operator.ai.thread_assist');

        $this->actingAs($this->member)->get(route('profile.show', ['tab' => 'billing']))->assertOk()->assertSee('data-ai-usage', false);
    }

    public function test_it_is_not_shown_on_the_other_tabs(): void
    {
        $this->grantEntitlement($this->member, 'ai_chat.access');

        $this->actingAs($this->member)->get(route('profile.show', ['tab' => 'details']))->assertOk()->assertDontSee('data-ai-usage', false);
    }

    public function test_the_embedding_usage_screen_is_for_those_who_may_see_the_audit_trail(): void
    {
        AiEmbeddingUsage::query()->create(['provider' => 'openai', 'model' => 'm', 'context' => 'doc_page_index', 'input_tokens' => 10, 'estimated_cost_usd' => 0.001, 'succeeded' => true]);
        $auditor = User::factory()->create();
        $this->grantEntitlement($auditor, 'operator.dashboard.access');
        $this->grantEntitlement($auditor, 'operator.audit.view');

        $this->actingAs($auditor)->get('/dashboard/embedding-usages')->assertOk()->assertSee('AI usage');
        $this->actingAs($this->member)->get('/dashboard/embedding-usages')->assertRedirect();
    }
}
