<?php

namespace App\Api;

use Filagora\Plugins\Api\OpenApiSpec;
use Symfony\Component\Yaml\Yaml;

/**
 * The plugin marketplace's OpenAPI document: the plugins package's description of its routes, with this
 * site's title, address and Zen Cart fields laid over it (`docs/openapi-zencart-overlay.yaml`). The
 * package keeps the contract for the routes and a test there keeps it in step with them; this class
 * only adds what the Zen Cart profile contributes.
 */
class PluginMarketplaceSpec
{
    /**
     * @return array<string, mixed>
     */
    public function document(): array
    {
        /** @var array<string, mixed> $package */
        $package = Yaml::parseFile(OpenApiSpec::path());
        /** @var array<string, mixed> $overlay */
        $overlay = Yaml::parseFile(base_path('docs/openapi-zencart-overlay.yaml'));

        $document = array_replace_recursive($package, $overlay);

        $document['info']['title'] = 'Zen Cart Plugin Marketplace API';
        $document['info']['summary'] = 'Plugin catalogue, releases, downloads, update checks and the changes owners and staff make, for Zen Cart sites and tools.';
        // The package's first paragraph addresses the people who install it; the rest is for callers.
        $paragraphs = explode("\n\n", rtrim((string) $package['info']['description']), 2);
        $document['info']['description'] = "The JSON API of this site's plugin library: the catalogue, releases, downloads, update checks, and the changes owners and staff make.\n\n"
            .($paragraphs[1] ?? $paragraphs[0])."\n\n"
            ."**Zen Cart.** Plugins carry the id deployed stores know them by (`legacy_vbulletin_file_id`) and an install folder; releases say whether they are encapsulated and which Zen Cart versions they work with. The update check takes a Zen Cart version as its `target`.\n";
        $document['servers'] = [['url' => rtrim((string) config('app.url'), '/'), 'description' => 'This site']];

        return $document;
    }

    public function yaml(): string
    {
        return Yaml::dump($this->document(), 20, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    }
}
