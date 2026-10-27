<?php

namespace App\Jobs;

use App\Services\Mailtrap\MailtrapWebhookHandler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessMailtrapWebhookEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly array $eventData
    ) {
        $this->onQueue('webhooks');
    }

    public function handle(MailtrapWebhookHandler $handler): void
    {
        $handler->handle($this->eventData);
    }
}
