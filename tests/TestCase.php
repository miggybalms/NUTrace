<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\UploadedFile;

abstract class TestCase extends BaseTestCase
{
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
