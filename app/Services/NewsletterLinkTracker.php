<?php

namespace App\Services;

use App\Models\Newsletter;
use Illuminate\Support\Facades\URL;

class NewsletterLinkTracker
{
    public function trackedUrl(Newsletter $newsletter, string $blockKey, ?int $newsletterSendId): string
    {
        $parameters = [
            'newsletter' => $newsletter->getKey(),
            'blockKey' => $blockKey,
        ];

        if ($newsletterSendId !== null) {
            $parameters['send'] = $newsletterSendId;
        }

        return URL::signedRoute('newsletter.link-click', $parameters);
    }
}
