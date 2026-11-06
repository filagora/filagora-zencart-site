<?php

namespace Tests\Support;

use Filagora\Ai\Contracts\ChatProvider;
use Generator;
use RuntimeException;

/**
 * A real (non-Mockery) test double for ChatProvider - streamReply() must
 * return an actual Generator, which Mockery can't fabricate, so tests bind
 * this into the container in place of a real provider.
 */
class FakeChatProvider implements ChatProvider
{
    /**
     * @param  list<string>  $deltas
     */
    public function __construct(
        private readonly array $deltas = ['Hello', ' there'],
        private readonly bool $shouldFail = false,
    ) {}

    public function providerName(): string
    {
        return 'fake';
    }

    public function model(): string
    {
        return 'fake-model';
    }

    public function streamReply(array $messages, ?string $context = null, ?int $userId = null): Generator
    {
        if ($this->shouldFail) {
            throw new RuntimeException('fake provider failure');
        }

        foreach ($this->deltas as $delta) {
            yield $delta;
        }

        return ['input_tokens' => 10, 'output_tokens' => 5];
    }
}
