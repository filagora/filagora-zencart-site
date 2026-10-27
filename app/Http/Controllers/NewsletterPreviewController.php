<?php

namespace App\Http\Controllers;

use App\Mail\NewsletterCampaignMail;
use App\Models\Newsletter;
use App\Services\NewsletterDeliveryTransport;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class NewsletterPreviewController extends Controller
{
    public function __invoke(Request $request, Newsletter $newsletter, NewsletterDeliveryTransport $transport): Response
    {
        abort_unless(Gate::forUser($request->user())->allows('view', $newsletter), 403);

        $html = (new NewsletterCampaignMail(
            newsletter: $newsletter,
            unsubscribeUrl: route('newsletter.show'),
            preview: true,
            deliveryProvider: $transport->provider(),
        ))->render();

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }
}
