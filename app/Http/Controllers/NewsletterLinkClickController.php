<?php

namespace App\Http\Controllers;

use App\Models\Newsletter;
use App\Models\NewsletterLinkClick;
use App\Models\NewsletterSend;
use App\Services\NewsletterContentRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NewsletterLinkClickController extends Controller
{
    public function __invoke(Request $request, Newsletter $newsletter, string $blockKey, NewsletterContentRenderer $renderer): RedirectResponse
    {
        $link = $renderer->resolveBlockLink($newsletter, $blockKey);

        abort_if($link === null || ! Str::startsWith($link['url'], ['http://', 'https://']), 404);

        $sendId = $request->integer('send') ?: null;
        $send = $sendId !== null ? NewsletterSend::query()->find($sendId) : null;

        NewsletterLinkClick::query()->create([
            'newsletter_id' => $newsletter->id,
            'newsletter_send_id' => $send?->id,
            'newsletter_block_id' => $link['newsletter_block_id'],
            'block_key' => $blockKey,
            'label' => $link['label'],
            'destination_url' => $link['url'],
            'clicked_at' => now(),
            'ip_hash' => hash('sha256', $request->ip().config('app.key')),
        ]);

        return redirect()->away($link['url']);
    }
}
