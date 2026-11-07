<?php

namespace App\Ai;

use Filagora\AiForums\Assist\ThreadAssistGate;
use Filagora\Forums\Models\Thread;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Thread assist is an optional package: the site registers its route and shows its button only when
 * the package is installed, and the thread page asks this, not the package, whether to show it.
 */
class ThreadAssistFeature
{
    public static function installed(): bool
    {
        return class_exists(ThreadAssistGate::class);
    }

    public function availableFor(?Authenticatable $user, Thread $thread): bool
    {
        return self::installed() && app(ThreadAssistGate::class)->allows($user, $thread);
    }
}
