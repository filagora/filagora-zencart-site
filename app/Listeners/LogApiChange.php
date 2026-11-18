<?php

namespace App\Listeners;

use Filagora\Plugins\Events\ApiChangeMade;
use Illuminate\Support\Facades\Log;

/**
 * A record of every change made through the plugin API: who, to which plugin, with which key. The
 * plugins package fires the event after a change goes through and stores nothing itself.
 *
 * The method is not called `handle`: Laravel discovers that name and would call it a second time
 * beside the registration in AppServiceProvider.
 */
class LogApiChange
{
    public function record(ApiChangeMade $event): void
    {
        Log::channel('api-audit')->info('api.change', [
            'change' => $event->change->value,
            'user_id' => $event->user->getAuthIdentifier(),
            'plugin' => $event->pluginSlug,
            'version' => $event->version,
            'token_id' => $event->tokenId,
            'ip' => $event->ip,
            'context' => $event->context,
        ]);
    }
}
