<?php

namespace Tests\Feature;

use App\Search\ForumSearchIndex;
use App\Search\Relevance;
use Filagora\Forums\Models\ForumGroup;
use Filagora\Search\Support\SearchIndexRegistry;
use Filagora\ZenCart\Models\Forum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class SiteSearchRankingTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_relevance_scores_whole_word_starts_above_exact_matches_as_the_old_site_did(): void
    {
        $tokens = Relevance::tokens('Payment');

        $this->assertSame(96, Relevance::score('Payment', $tokens, 12));
        $this->assertSame(132, Relevance::score('Payment gateways', $tokens, 12));
        $this->assertSame(36, Relevance::score('Prepayments', $tokens, 12));
        $this->assertSame(0, Relevance::score('Shipping', $tokens, 12));
    }

    public function test_freshness_bonus_steps_down_with_age(): void
    {
        $this->assertSame(60, Relevance::freshnessBonus(now()->subDays(5)));
        $this->assertSame(30, Relevance::freshnessBonus(now()->subDays(300)));
        $this->assertSame(0, Relevance::freshnessBonus(now()->subYears(20)));
        $this->assertSame(0, Relevance::freshnessBonus(null));
    }

    public function test_the_forums_index_is_registered_with_the_search_package(): void
    {
        $this->assertTrue(app(SearchIndexRegistry::class)->has(ForumSearchIndex::NAME));
    }

    public function test_forum_results_are_ordered_by_the_old_relevance_score(): void
    {
        $group = ForumGroup::query()->create(['name' => 'Main', 'slug' => 'main', 'is_active' => true]);

        foreach ([['Payment', 1], ['Payment gateways', 2], ['Hidden payment forum', 3]] as [$name, $order]) {
            Forum::query()->create([
                'forum_group_id' => $group->id,
                'name' => $name,
                'slug' => str($name)->slug()->toString(),
                'is_active' => $name !== 'Hidden payment forum',
                'sort_order' => $order,
            ]);
        }

        $this->get('/search?q=payment')
            ->assertOk()
            ->assertSeeInOrder(['Payment gateways', 'Payment'])
            ->assertDontSee('Hidden payment forum');
    }
}
