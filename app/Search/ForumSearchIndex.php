<?php

namespace App\Search;

use Filagora\Forums\Models\Forum;
use Filagora\Forums\Support\Models as ForumModels;
use Filagora\Search\Contracts\SearchIndex;
use Filagora\Search\Support\Document;
use Filagora\Search\Support\Hit;
use Filagora\Search\Support\IndexDefinition;
use Filagora\Search\Support\SearchQuery;
use Filagora\Search\Support\SearchResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Forums as search documents (name, group name, description), so the site-wide search finds a forum
 * by its words the way the old Meilisearch-backed search did. Active-ness is a filter, because a
 * forum's visibility to the viewer is decided on the row after the search.
 */
final class ForumSearchIndex implements SearchIndex
{
    public const NAME = 'forums';

    public function definition(): IndexDefinition
    {
        return new IndexDefinition(
            name: self::NAME,
            searchable: ['name', 'group_name', 'description', 'slug'],
            filterable: ['is_active'],
        );
    }

    public function model(): string
    {
        return ForumModels::class('forum');
    }

    public function shouldBeSearchable(Model $model): bool
    {
        return $model instanceof Forum;
    }

    public function toDocument(Model $model): Document
    {
        assert($model instanceof Forum);

        return new Document($model->getKey(), [
            'name' => (string) $model->name,
            'slug' => (string) $model->slug,
            'group_name' => (string) ($model->group?->name ?? ''),
            'description' => (string) ($model->description ?? ''),
            'is_active' => (bool) $model->is_active,
        ]);
    }

    public function query(): Builder
    {
        return ForumModels::class('forum')::query()->with('group:id,name');
    }

    public function expectedCount(): int
    {
        return ForumModels::class('forum')::query()->count();
    }

    public function fallback(SearchQuery $query): SearchResult
    {
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query->text).'%';

        $ids = ForumModels::class('forum')::query()
            ->where('is_active', true)
            ->where(fn (Builder $match) => $match->where('name', 'like', $like)->orWhere('description', 'like', $like))
            ->orderBy('sort_order')
            ->limit($query->perPage)
            ->pluck('id');

        return new SearchResult($ids->map(fn (mixed $id): Hit => new Hit($id))->all(), $ids->count());
    }
}
