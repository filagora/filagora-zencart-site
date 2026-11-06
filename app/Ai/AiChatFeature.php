<?php

namespace App\Ai;

use Filagora\Ai\Services\AiChatService;

/**
 * The AI chat is an optional package: the site registers its routes only when the package is installed.
 */
class AiChatFeature
{
    public static function installed(): bool
    {
        return class_exists(AiChatService::class);
    }
}
