<?php

namespace Tests\Concerns;

use Filagora\Cms\Models\CmsMediaAsset;
use Illuminate\Support\Facades\Storage;

/**
 * The CMS package only gives a media asset a public URL when its file exists, so a test that
 * creates an asset row gets a stand-in file written for it on a faked disk.
 */
trait FakesCmsMediaFiles
{
    private static bool $mediaFileListenerRegistered = false;

    protected function setUpFakesCmsMediaFiles(): void
    {
        Storage::fake('public');

        if (! self::$mediaFileListenerRegistered) {
            self::$mediaFileListenerRegistered = true;

            CmsMediaAsset::saved(static function (CmsMediaAsset $asset): void {
                Storage::disk((string) $asset->disk)->put((string) $asset->path, 'stand-in');
            });
        }
    }
}
