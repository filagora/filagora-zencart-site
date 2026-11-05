<?php

namespace App\Http;

use App\Http\Middleware\EnsureSubsystemIsAvailable;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;

/**
 * Which addresses each maintenance switch takes down. Some of them belong to packages and the
 * profile, so a route group here could not reach them: the switch is added to each route once every
 * route has loaded. An address in both lists answers to both switches.
 */
class SubsystemRoutes
{
    /** @var array<string, list<string>> */
    public const URIS = [
        'forums' => [
            'attachment.php', 'forum/attachment.php',
            'bug-reports/{bugReport}',
            'forum/forumdisplay.php/{legacyPath?}', 'forum/newreply.php/{legacyPath?}', 'forum/printthread.php/{legacyPath?}',
            'forum/showpost.php/{legacyPath?}', 'forum/showthread.php/{legacyPath?}',
            'forumdisplay.php/{legacyPath?}', 'newreply.php/{legacyPath?}', 'printthread.php/{legacyPath?}',
            'showpost.php/{legacyPath?}', 'showthread.php/{legacyPath?}',
            'forums', 'forums/mark-all-read', 'forums/search', 'forums/search/for-move', 'forums/search/forums',
            'forums/today', 'forums/unread', 'forums/{forum}', 'forums/{forum}/subscribe', 'forums/{forum}/threads',
            'forums/{forum}/threads/new', 'forums/{forum}/unsubscribe',
            'members/{user}/posts', 'members/{user}/threads', 'misc/{thread}',
            'moderation/relocate/undo', 'moderation/reports/{postReport}/resolve',
            'plugins/submit', 'plugins/support-threads/search',
            'polls/{poll}', 'polls/{poll}/edit', 'polls/{poll}/moderate', 'polls/{poll}/vote',
            'post-attachments/inline-upload', 'post-attachments/{attachment}', 'post-attachments/{attachment}/thumbnail',
            'posts/{post}', 'posts/{post}/edit', 'posts/{post}/moderate', 'posts/{post}/reactions/toggle', 'posts/{post}/report',
            'profile/subscriptions/bulk-remove', 'profile/subscriptions/{thread}/remove',
            'threads/search/for-move', 'threads/{thread}', 'threads/{thread}/bug-reports', 'threads/{thread}/moderate',
            'threads/{thread}/poll', 'threads/{thread}/poll/new', 'threads/{thread}/posts/moderate-bulk', 'threads/{thread}/reply',
            'threads/{thread}/search', 'threads/{thread}/subscribe', 'threads/{thread}/unsubscribe',
        ],
        'plugins' => [
            'api/v1/plugin-types', 'api/v1/plugins', 'api/v1/plugins/updates', 'api/v1/plugins/{slug}',
            'api/v1/plugins/{slug}/download/{version}', 'api/v1/plugins/{slug}/releases', 'api/v1/plugins/{slug}/releases/{version}',
            'api/v1/plugins/{slug}/releases/{version}/moderate', 'api/v1/plugins/{slug}/subscription',
            'downloads.php', 'forum/downloads.php', 'plugin_check.php/{legacyPath?}', 'plugincheck/{legacyPath}',
            'plugins', 'plugins/search', 'plugins/submit', 'plugins/support-threads/search', 'plugins/type/{slug}',
            'plugins/{plugin}', 'plugins/{plugin}/download', 'plugins/{plugin}/grant-auto-publish',
            'plugins/{plugin}/moderate', 'plugins/{plugin}/releases/new', 'plugins/{plugin}/releases/{release}',
            'plugins/{plugin}/releases/{release}/download', 'plugins/{plugin}/releases/{release}/grant-auto-publish',
            'plugins/{plugin}/releases/{release}/moderate', 'plugins/{plugin}/releases/{release}/zc-versions',
            'plugins/{plugin}/report', 'plugins/{plugin}/subscribe', 'plugins/{plugin}/unsubscribe',
            'profile/plugin-subscriptions/{plugin}/remove',
        ],
    ];

    public static function assign(Router $router): void
    {
        foreach ($router->getRoutes()->getRoutes() as $route) {
            self::assignTo($route);
        }
    }

    private static function assignTo(Route $route): void
    {
        foreach (self::URIS as $subsystem => $uris) {
            if (in_array($route->uri(), $uris, true)) {
                $route->middleware(EnsureSubsystemIsAvailable::class.':'.$subsystem);
            }
        }
    }
}
