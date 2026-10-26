<?php

namespace App\Http\Controllers\Api;

use App\Api\ApiRequestUserResolver;
use App\Http\Controllers\Controller;
use Filagora\Cms\Services\CmsContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The published CMS content over JSON. Public, but a request that carries an API key needs the
 * `cms:read` ability on it.
 */
class CmsApiController extends Controller
{
    public function __construct(
        private readonly CmsContent $cms,
        private readonly ApiRequestUserResolver $callers,
    ) {}

    public function pages(Request $request): JsonResponse
    {
        $this->authorizeRead($request);

        $tag = $request->query('tag');

        return response()->json([
            'data' => $this->cms->publishedPages(is_string($tag) ? $tag : null)->makeHidden(['preview_token']),
        ]);
    }

    public function page(string $slug, Request $request): JsonResponse
    {
        $this->authorizeRead($request);

        $preview = $request->query('preview');
        $page = $this->cms->publishedPageBySlug($slug, is_string($preview) ? $preview : null);

        abort_if($page === null, 404);

        return response()->json(['data' => $page->makeHidden(['preview_token'])]);
    }

    public function navigation(string $name, Request $request): JsonResponse
    {
        $this->authorizeRead($request);

        $navigation = $this->cms->navigation($name);

        abort_if($navigation === null, 404);

        return response()->json([
            'data' => [
                'id' => (int) $navigation->id,
                'name' => (string) $navigation->name,
                'items' => $this->items($navigation->items),
                'created_at' => $navigation->created_at,
                'updated_at' => $navigation->updated_at,
            ],
        ]);
    }

    public function block(string $slug, Request $request): JsonResponse
    {
        $this->authorizeRead($request);

        $block = $this->cms->publishedReusableBlock($slug);

        abort_if($block === null, 404);

        return response()->json([
            'data' => [
                'id' => (int) $block->id,
                'slug' => (string) $block->slug,
                'type' => $block->type instanceof \BackedEnum ? $block->type->value : $block->type,
                'name' => (string) ($block->name ?? ''),
                'sort_order' => (int) $block->sort_order,
                'data' => $block->data,
            ],
        ]);
    }

    private function authorizeRead(Request $request): void
    {
        $this->callers->ensureAbilityForBearerToken($request, $this->callers->resolve($request), 'cms:read');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function items(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $normalized = [];

        foreach ($items as $item) {
            if (is_array($item)) {
                $normalized[] = [
                    'children' => $this->items($item['children'] ?? []),
                    'label' => (string) ($item['label'] ?? ''),
                    'reference' => (string) ($item['reference'] ?? ''),
                    'target' => (string) ($item['target'] ?? '_self'),
                    'type' => (string) ($item['type'] ?? 'url'),
                    'url' => (string) ($item['url'] ?? '#'),
                ];
            }
        }

        return $normalized;
    }
}
