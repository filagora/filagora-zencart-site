<?php

namespace Tests\Feature\Ai;

use Filagora\Ai\Retrieval\RetrievalSources;
use Filagora\Ai\Services\GroundingPrompt;
use Filagora\Search\Support\SearchIndexRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CorporaWiringTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_chat_looks_in_code_then_documentation_then_threads_then_plugins(): void
    {
        $keys = array_map(fn ($source): string => $source->key(), app(RetrievalSources::class)->all());

        $this->assertSame(['code', 'docs', 'threads', 'plugins'], $keys);
    }

    public function test_the_prompt_carries_the_rules_for_code_excerpts(): void
    {
        $this->assertStringContainsString('Excerpts marked CODE', app(GroundingPrompt::class)->rules());
    }

    public function test_the_corpora_are_search_indexes(): void
    {
        $names = array_keys(app(SearchIndexRegistry::class)->all());

        foreach (['doc_pages', 'code_chunks', 'plugin_release_vectors'] as $name) {
            $this->assertContains($name, $names);
        }
    }

    public function test_their_tables_exist(): void
    {
        foreach (['doc_pages', 'code_chunks', 'code_chunk_locations'] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table);
        }
    }

    public function test_the_import_and_ingest_commands_exist(): void
    {
        $commands = array_keys(Artisan::all());

        $this->assertContains('docs:import', $commands);
        $this->assertContains('zencart:ingest-code', $commands);
    }

    public function test_the_docs_are_linked_to_this_sites_documentation_unless_the_environment_says_otherwise(): void
    {
        $this->assertSame('https://docs.zen-cart.com', config('filagora-zencart.docs.base_url'));
        $this->assertSame('https://github.com/zencart/documentation.git', config('filagora-zencart.docs.repo_url'));
    }

    public function test_the_code_corpus_indexes_the_versions_the_live_site_does(): void
    {
        $releases = array_values(array_filter(config('filagora-zencart.code.tags'), fn ($value, $key): bool => is_int($key), ARRAY_FILTER_USE_BOTH));

        $this->assertSame(['v2.0.0', 'v2.0.1', 'v2.1.0', 'v2.2.1', 'v2.2.2'], $releases);
        $this->assertSame('master', config('filagora-zencart.code.tags')['v3.0-dev']);
        $this->assertFalse(config('filagora-zencart.code.enabled'));
    }
}
