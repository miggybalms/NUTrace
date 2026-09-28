<?php

namespace Tests\Feature;

use App\Support\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Uploads are the one thing that fails silently in this app: the media disk
 * returns false instead of throwing, the asset is saved anyway and the UI
 * falls back to "No image". The deployed site lost photos that way for weeks
 * without a single log line, so both the guard and the command that exposes
 * the real reason are covered here.
 */
class MediaDoctorTest extends TestCase
{
    public function test_media_doctor_names_the_local_disk_as_the_cause_of_missing_images(): void
    {
        config([
            'filesystems.disks.public.driver' => 'local',
            'filesystems.disks.public.root' => storage_path('app/public'),
        ]);

        $this->artisan('media:doctor')
            ->expectsOutputToContain('The media disk is the LOCAL disk')
            ->assertFailed();
    }

    public function test_a_rejected_upload_is_logged_instead_of_silently_losing_the_photo(): void
    {
        // A disk whose root is a plain file cannot accept writes: this is the
        // same shape as a bucket that refuses the key, minus the network.
        $notADirectory = storage_path('app/media-doctor-test-root');

        file_put_contents($notADirectory, 'not a directory');

        config([
            'filesystems.disks.public.driver' => 'local',
            'filesystems.disks.public.root' => $notADirectory,
        ]);

        Log::shouldReceive('error')->atLeast()->once();

        $path = Media::storeUploadedFile(
            UploadedFile::fake()->create('photo.jpg', 10, 'image/jpeg'),
            'assets'
        );

        $this->assertNull($path, 'A rejected upload must return null so the caller can carry on.');

        @unlink($notADirectory);
    }
}
