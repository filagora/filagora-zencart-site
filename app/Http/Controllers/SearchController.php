<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Search\SearchResultType;
use App\Search\SiteSearch;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The site-wide search page: a few results from each of forums, threads, plugins and pages, with a
 * filter by kind and links on to the fuller searches (threads with filters, all plugins).
 */
class SearchController extends Controller
{
    public function __construct(private readonly SiteSearch $search) {}

    public function index(Request $request): View
    {
        // Array-shaped input (?q[]=x) reads as empty rather than failing the cast.
        $rawQuery = $request->query('q', '');
        $query = $this->search->normalize(is_scalar($rawQuery) ? (string) $rawQuery : '');
        $rawType = $request->query('type', '');
        $selectedType = SearchResultType::tryFrom(is_scalar($rawType) ? (string) $rawType : '');
        $searchWasSkipped = $query !== '' && mb_strlen($query) < SiteSearch::MINIMUM_QUERY_LENGTH;
        $viewer = $request->user() instanceof User ? $request->user() : null;

        $sections = $query !== '' && ! $searchWasSkipped
            ? $this->search->sections($query, $viewer)
            : $this->search->emptySections();

        $sourceCounts = [];

        foreach ($sections as $section) {
            if ($section['items'] !== []) {
                $sourceCounts[$section['source']] = count($section['items']);
            }
        }

        $visible = $sections;

        if ($selectedType instanceof SearchResultType) {
            foreach ($visible as $index => $section) {
                if ($index !== $selectedType->sectionIndex()) {
                    $visible[$index]['items'] = [];
                }
            }
        }

        return view('search.index', [
            'query' => $query,
            'sections' => $visible,
            'sourceCounts' => $sourceCounts,
            'selectedType' => $selectedType,
            'totalResults' => array_sum(array_map(fn (array $section): int => count($section['items']), $visible)),
            'searchWasSkipped' => $searchWasSkipped,
            'minimumQueryLength' => SiteSearch::MINIMUM_QUERY_LENGTH,
        ]);
    }
}
