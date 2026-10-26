<?php

namespace App\Http\Controllers;

use App\Support\FormattedContentRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarkdownPreviewController extends Controller
{
    public function __construct(private readonly FormattedContentRenderer $contentRenderer) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:20000'],
        ]);

        return response()->json([
            'html' => $this->contentRenderer->renderUncached((string) ($validated['body'] ?? '')),
        ]);
    }
}
