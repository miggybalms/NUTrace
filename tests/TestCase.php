<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\UploadedFile;

abstract class TestCase extends BaseTestCase
{
    /**
     * Every test starts with no sticker text left over from another test's
     * database. Both caches are per-process and keyed by asset code, and a
     * refreshed database makes a stale token point at a row that no longer
     * exists - a flake that has nothing to do with the code under test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        \App\Support\AssetTag::clearCache();
        \App\Support\AssetQr::clearCache();
    }

    /**
     * A real 1x1 PNG, for the photo every request form now requires.
     *
     * The `image` rule reads the file itself and the PHP build here has no GD,
     * so the bytes have to be a genuine image rather than a fabricated one.
     */
    protected function requestPhoto(string $name = 'asset-photo.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8AAAwAB/AF+g7oAAAAASUVORK5CYII=')
        );
    }
}
