<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Filament panel path
    |--------------------------------------------------------------------------
    |
    | The URL prefix the admin panel is served from, without slashes. Set
    | SITE_PANEL_PATH to move it (for example to "dashboard"). The site's own
    | pages use "/".
    |
    */

    'panel_path' => env('SITE_PANEL_PATH', 'dashboard'),

    /*
    |--------------------------------------------------------------------------
    | Formatted content cache
    |--------------------------------------------------------------------------
    |
    | Seconds rendered post and notice HTML is reused before it is parsed again. The variable keeps
    | the name the live site's environment file already uses.
    |
    */

    'formatted_content_cache_ttl_seconds' => (int) env('FORMATTED_CONTENT_CACHE_TTL_SECONDS', 86400),

];
