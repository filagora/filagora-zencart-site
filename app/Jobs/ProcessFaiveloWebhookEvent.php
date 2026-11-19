<?php

namespace App\Jobs;

use App\Services\Faivelo\FaiveloWebhookHandler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessFaiveloWebhookEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  array<string, mixed>  $eventData
     */
    public function __construct(
        public readonly array $eventData
    ) {
        $this->onQueue('webhooks');
    }

    public function handle(FaiveloWebhookHandler $handler): void
    {
        $handler->handle($this->eventData);
    }
}
