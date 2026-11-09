<?php

return [
    // The search circuit breaker records that the engine is down, which a deploy's cache flush must
    // not forget. The old site kept it in the `durable` store; so does this one (CACHE_DURABLE_STORE). Config
    // merging is shallow, so the cooldown is repeated here.
    'circuit' => [
        'cooldown_seconds' => 60,
        'store' => env('CACHE_DURABLE_STORE') ?: null,
    ],
];
