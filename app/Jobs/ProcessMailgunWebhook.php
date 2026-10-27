<?php

namespace App\Jobs;

use App\Services\Mailgun\MailgunWebhookHandler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessMailgunWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly array $eventData
    ) {
        $this->onQueue('webhooks');
    }

    public function handle(MailgunWebhookHandler $handler): void
    {
        $handler->handle($this->eventData);
    }
}
