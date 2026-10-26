<?php

namespace App\Search;

/**
 * The four kinds of result the site-wide search page groups, in the order it lists them.
 */
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

    public function sourceLabel(): string
    {
        return match ($this) {
            self::Forum => 'Forum',
            self::Thread => 'Thread',
            self::PluginRelease => 'Plugin Release',
            self::CmsPage => 'CMS Page',
        };
    }

    public function sectionTitle(): string
    {
        return match ($this) {
            self::Forum => 'Forums',
            self::Thread => 'Threads',
            self::PluginRelease => 'Plugin Releases',
            self::CmsPage => 'CMS Pages',
        };
    }

    public function sectionIndex(): int
    {
        return array_search($this, self::cases(), true);
    }
}
