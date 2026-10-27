<?php

namespace App\Services;

use Filagora\Cms\Models\CmsMediaAsset;
use Filagora\Core\Models\Setting;
use Filagora\Core\Settings\SettingsRegistry;

class NewsletterBrandingService
{
    public const BRAND_NAME_KEY = 'newsletter_brand_name';

    public const HEADER_EYEBROW_KEY = 'newsletter_header_eyebrow';

    public const LOGO_ASSET_ID_KEY = 'newsletter_logo_asset_id';

    public const LOGO_ALT_KEY = 'newsletter_logo_alt';

    public const FOOTER_TEXT_KEY = 'newsletter_footer_text';

    public const MANAGE_LINK_LABEL_KEY = 'newsletter_manage_link_label';

    /**
     * @return array<string, string|null>
     */
    public function defaults(): array
    {
        return [
            self::BRAND_NAME_KEY => 'Zen Cart',
            self::HEADER_EYEBROW_KEY => 'Zen Cart Newsletter',
            self::LOGO_ASSET_ID_KEY => null,
            self::LOGO_ALT_KEY => 'Zen Cart',
            self::FOOTER_TEXT_KEY => 'You are receiving this because you subscribed to the Zen Cart newsletter.',
            self::MANAGE_LINK_LABEL_KEY => 'Unsubscribe or manage newsletter signup',
        ];
    }

    /**
     * @return array{
     *     brand_name: string,
     *     header_eyebrow: string,
     *     logo_asset_id: ?int,
     *     logo_alt: string,
     *     logo_url: ?string,
     *     footer_text: string,
     *     manage_link_label: string
     * }
     */
    public function settings(): array
    {
        $defaults = $this->defaults();
        $logoAssetId = $this->logoAssetId();
        $logoAsset = $logoAssetId !== null ? CmsMediaAsset::query()->find($logoAssetId) : null;

        return [
            'brand_name' => trim((string) app(SettingsRegistry::class)->get(self::BRAND_NAME_KEY, $defaults[self::BRAND_NAME_KEY])) ?: (string) $defaults[self::BRAND_NAME_KEY],
            'header_eyebrow' => trim((string) app(SettingsRegistry::class)->get(self::HEADER_EYEBROW_KEY, $defaults[self::HEADER_EYEBROW_KEY])) ?: (string) $defaults[self::HEADER_EYEBROW_KEY],
            'logo_asset_id' => $logoAssetId,
            'logo_alt' => trim((string) app(SettingsRegistry::class)->get(self::LOGO_ALT_KEY, $defaults[self::LOGO_ALT_KEY])) ?: (string) $defaults[self::LOGO_ALT_KEY],
            'logo_url' => $logoAsset?->publicUrl(),
            'footer_text' => trim((string) app(SettingsRegistry::class)->get(self::FOOTER_TEXT_KEY, $defaults[self::FOOTER_TEXT_KEY])) ?: (string) $defaults[self::FOOTER_TEXT_KEY],
            'manage_link_label' => trim((string) app(SettingsRegistry::class)->get(self::MANAGE_LINK_LABEL_KEY, $defaults[self::MANAGE_LINK_LABEL_KEY])) ?: (string) $defaults[self::MANAGE_LINK_LABEL_KEY],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formData(): array
    {
        $settings = $this->settings();

        return [
            'brand_name' => $settings['brand_name'],
            'header_eyebrow' => $settings['header_eyebrow'],
            'logo_asset_id' => $settings['logo_asset_id'],
            'logo_alt' => $settings['logo_alt'],
            'footer_text' => $settings['footer_text'],
            'manage_link_label' => $settings['manage_link_label'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(array $data): void
    {
        $this->upsert(self::BRAND_NAME_KEY, 'Newsletter Brand Name', (string) ($data['brand_name'] ?? ''), 'string', 'Shared newsletter brand name.');
        $this->upsert(self::HEADER_EYEBROW_KEY, 'Newsletter Header Eyebrow', (string) ($data['header_eyebrow'] ?? ''), 'string', 'Small shared text shown above each newsletter subject.');
        $this->upsert(self::LOGO_ASSET_ID_KEY, 'Newsletter Logo Asset ID', $this->normalizeNullableInt($data['logo_asset_id'] ?? null), 'integer', 'Optional CMS media asset used as the shared newsletter logo.');
        $this->upsert(self::LOGO_ALT_KEY, 'Newsletter Logo Alt Text', (string) ($data['logo_alt'] ?? ''), 'string', 'Alt text for the shared newsletter logo.');
        $this->upsert(self::FOOTER_TEXT_KEY, 'Newsletter Footer Text', (string) ($data['footer_text'] ?? ''), 'text', 'Shared footer text shown on all newsletters.');
        $this->upsert(self::MANAGE_LINK_LABEL_KEY, 'Newsletter Manage Link Label', (string) ($data['manage_link_label'] ?? ''), 'string', 'Shared unsubscribe/manage link label.');
    }

    /**
     * @return array<int, string>
     */
    public function imageAssetOptions(): array
    {
        return CmsMediaAsset::query()
            ->where('mime_type', 'like', 'image/%')
            ->orderBy('filename')
            ->get()
            ->mapWithKeys(fn (CmsMediaAsset $asset): array => [
                $asset->id => trim($asset->filename.($asset->alt_text ? ' - '.$asset->alt_text : '')),
            ])
            ->all();
    }

    private function logoAssetId(): ?int
    {
        $value = app(SettingsRegistry::class)->get(self::LOGO_ASSET_ID_KEY);

        return is_numeric($value) ? (int) $value : null;
    }

    private function normalizeNullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function upsert(string $key, string $label, mixed $value, string $valueType, string $description): void
    {
        Setting::query()->updateOrCreate(
            ['key' => $key],
            [
                'label' => $label,
                'value' => $value === null ? null : (string) $value,
                'value_type' => $valueType,
                'is_public' => false,
                'description' => $description,
            ]
        );
    }
}
