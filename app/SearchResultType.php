<?php

namespace App;

enum SearchResultType: string
{
    case Forum = 'forum';
    case Thread = 'thread';
    case PluginRelease = 'plugin_release';
    case CmsPage = 'cms_page';

    public function label(): string
    {
        return match ($this) {
            self::Forum => 'Forums',
            self::Thread => 'Threads',
            self::PluginRelease => 'Plugins',
            self::CmsPage => 'Docs',
        };
    }

    /**
     * Matches SearchController::emptySections()'s 'source' value for this type.
     */
    public function sourceLabel(): string
    {
        return match ($this) {
            self::Forum => 'Forum',
            self::Thread => 'Thread',
            self::PluginRelease => 'Plugin Release',
            self::CmsPage => 'CMS Page',
        };
    }

    /**
     * Index into SearchController::emptySections()'s array for this type.
     */
    public function sectionIndex(): int
    {
        return match ($this) {
            self::Forum => 0,
            self::Thread => 1,
            self::PluginRelease => 2,
            self::CmsPage => 3,
        };
    }
}
