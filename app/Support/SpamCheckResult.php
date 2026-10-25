<?php

namespace App\Support;

class SpamCheckResult
{
    /**
     * @param  list<string>  $reasons
     * @param  list<string>  $sources
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public bool $isSpam = false,
        public bool $isSuspicious = false,
        public array $reasons = [],
        public array $sources = [],
        public array $details = [],
    ) {}

    /**
     * @param  array<string, mixed>  $details
     */
    public function flagSpam(string $source, string $reason, array $details = []): void
    {
        $this->isSpam = true;
        $this->isSuspicious = true;
        $this->reasons[] = $reason;
        $this->sources[] = $source;
        $this->details[$source] = $details;
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public function flagSuspicious(string $source, string $reason, array $details = []): void
    {
        $this->isSuspicious = true;
        $this->reasons[] = $reason;
        $this->sources[] = $source;
        $this->details[$source] = $details;
    }
}
