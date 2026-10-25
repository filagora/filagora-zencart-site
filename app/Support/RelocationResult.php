<?php

namespace App\Support;

/**
 * Outcome of a moderator relocation (move thread / move post / promote /
 * bulk / undo). Carries what the caller needs to build the response — the
 * place the content ended up, a one-shot token to reverse it, and a
 * human-readable summary for flash / toast copy.
 */
final readonly class RelocationResult
{
    public function __construct(
        public string $destinationUrl,
        public ?string $undoToken,
        public string $summary,
        public int $count = 1,
        /** The page the action ran from is gone (e.g. the source thread was emptied and removed) — the client should leave it. */
        public bool $navigateAway = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'destination_url' => $this->destinationUrl,
            'undo_token' => $this->undoToken,
            'message' => $this->summary,
            'count' => $this->count,
            'navigate_away' => $this->navigateAway,
        ];
    }
}
