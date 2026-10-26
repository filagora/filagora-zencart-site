<?php

namespace App\Support;

use Filagora\Plugins\Models\Plugin;

class PluginStructuredDataFactory
{
    public function __construct(private readonly StructuredDataPublisher $publisher) {}

    private const DESCRIPTION_LIMIT = 300;

    /**
     * JSON-LD for a public plugin detail page. Callers decide whether the
     * plugin is public; this only turns real plugin data into a
     * SoftwareApplication. It deliberately leaves out offers, ratings and any
     * download URL: downloads can be entitlement-gated and nothing here
     * should imply a price or a direct link.
     *
     * The application plus its breadcrumb trail, as separate JSON-LD documents.
     *
     * @return list<array<string, mixed>>
     */
    public function forPlugin(Plugin $plugin): array
    {
        $plugin->loadMissing(['type', 'owner:id,username,name', 'latestActiveRelease.zcVersions']);

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => trim((string) $plugin->name),
            'url' => route('plugins.show', $plugin),
        ];

        $description = $this->descriptionFor($plugin);
        if ($description !== '') {
            $data['description'] = $description;
        }

        $category = trim((string) $plugin->type?->name);
        if ($category !== '') {
            $data['applicationCategory'] = $category;
        }

        $release = $plugin->latestActiveRelease;
        if ($release !== null) {
            $version = trim((string) $release->version);
            if ($version !== '') {
                $data['softwareVersion'] = $version;
            }

            $zcVersions = $release->zcVersions
                ->pluck('version')
                ->map(fn ($version): string => trim((string) $version))
                ->filter()
                ->sort(SORT_NATURAL)
                ->values();

            if ($zcVersions->isNotEmpty()) {
                $data['softwareRequirements'] = 'Zen Cart '.$zcVersions->implode(', ');
            }

            $modifiedAt = $release->approved_at ?? $release->created_at;
            if ($modifiedAt !== null) {
                $data['dateModified'] = $modifiedAt->toIso8601String();
            }
        }

        if ($plugin->created_at !== null) {
            $data['datePublished'] = $plugin->created_at->toIso8601String();
        }

        $authorName = trim((string) ($plugin->owner?->username ?: $plugin->owner?->name ?: ''));
        if ($authorName !== '') {
            $data['author'] = ['@type' => 'Person', 'name' => $authorName];
        }

        $data['publisher'] = $this->publisher->organization();

        $trail = [['name' => 'Plugins', 'url' => route('plugins.index')]];
        if ($plugin->type !== null && trim((string) $plugin->type->name) !== '') {
            $trail[] = ['name' => trim((string) $plugin->type->name), 'url' => route('plugins.type', $plugin->type->slug)];
        }
        $trail[] = ['name' => $data['name'], 'url' => $data['url']];

        return [$data, $this->publisher->breadcrumbs($trail)];
    }

    private function descriptionFor(Plugin $plugin): string
    {
        return MetaDescription::fromMarkup($plugin->description, self::DESCRIPTION_LIMIT);
    }
}
