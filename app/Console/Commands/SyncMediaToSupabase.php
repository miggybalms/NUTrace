<?php

namespace App\Console\Commands;

use App\Support\Media;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Moves everything the app stores as media onto Supabase Storage.
 *
 *   php artisan media:sync            upload local files + brand + normalise URLs
 *   php artisan media:sync --files    upload local files only
 *   php artisan media:sync --brand    publish the logo mark and favicon only
 *   php artisan media:sync --urls     normalise stored URLs only
 *   php artisan media:sync --qr       also generate QR images that are missing
 *   php artisan media:sync --dry-run  report without writing anything
 */
class SyncMediaToSupabase extends Command
{
    protected $signature = 'media:sync
        {--files : Only upload local files to Supabase Storage}
        {--brand : Only publish the app\'s brand artwork (logo mark, favicon)}
        {--urls : Only normalise the URLs stored in the database}
        {--qr : Also generate the QR image for assets that do not have one}
        {--force : Re-upload files that already exist in the bucket}
        {--dry-run : Report what would change without writing anything}';

    protected $description = 'Upload profile photos, asset photos, request attachments, QR images and brand artwork to Supabase Storage, and point the stored URLs at them';

    public function handle(): int
    {
        if (config('filesystems.disks.public.driver') !== 'supabase') {
            $this->error('Supabase Storage is not configured.');
            $this->line('Set SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY in .env, then run: php artisan config:clear');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $onlyBrand = (bool) $this->option('brand');
        $onlyFiles = (bool) $this->option('files');
        $onlyUrls = (bool) $this->option('urls');

        // Brand artwork is a local file as well, so a plain run and --files
        // publish it too; --brand does it without touching anything else.
        $publishFiles = ! $onlyUrls && ! $onlyBrand;
        $publishBrand = ! $onlyUrls;
        $publishUrls = ! $onlyFiles && ! $onlyBrand;

        $this->line('Bucket: <info>'.config('filesystems.disks.public.bucket').'</info>'
            .'  ·  '.rtrim((string) config('filesystems.disks.public.endpoint'), '/'));

        if ($dry) {
            $this->warn('Dry run: nothing will be written.');
        }

        if ($publishFiles) {
            $this->newLine();
            $this->uploadLocalFiles($dry);
        }

        if ($publishBrand) {
            $this->newLine();
            $this->uploadBrandAssets($dry);
        }

        if ($publishUrls) {
            $this->newLine();
            $this->normaliseUrls($dry);
        }

        if ($this->option('qr')) {
            $this->newLine();
            $this->generateMissingQr($dry);
        }

        $this->newLine();
        $this->info($dry ? 'Dry run finished.' : 'Media sync finished.');

        return self::SUCCESS;
    }

    /**
     * Push every file under storage/app/public into the bucket.
     */
    protected function uploadLocalFiles(bool $dry): void
    {
        $root = storage_path('app/public');

        if (! File::isDirectory($root)) {
            $this->warn('No local media directory at '.$root);

            return;
        }

        $files = File::allFiles($root);
        $disk = Media::disk();
        $force = (bool) $this->option('force');

        $this->info('Local files: '.count($files));

        $uploaded = $skipped = $failed = 0;

        foreach ($files as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());

            try {
                if (! $force && $disk->exists($relative)) {
                    $skipped++;

                    continue;
                }
            } catch (Throwable $e) {
                $this->error('  ! '.$relative.' → '.($e->getPrevious()?->getMessage() ?: $e->getMessage()));
                $failed++;

                continue;
            }

            if ($dry) {
                $this->line('  + '.$relative);
                $uploaded++;

                continue;
            }

            try {
                $contents = File::get($file->getPathname());

                // No mimetype option: the adapter derives it from the extension.
                if ($disk->put($relative, $contents)) {
                    $uploaded++;
                    $this->line('  ↑ '.$relative);
                } else {
                    $failed++;
                    $this->error('  ! '.$relative.' could not be uploaded');
                }
            } catch (Throwable $e) {
                $failed++;
                $this->error('  ! '.$relative.' → '.$e->getMessage());
            }
        }

        $this->line(sprintf(
            '  uploaded %d · already stored %d · failed %d',
            $uploaded,
            $skipped,
            $failed
        ));
    }

    /**
     * Publish the app's own brand artwork to the bucket.
     *
     * Media::brand() reads these objects, so once they are here the logo can be
     * replaced by uploading one file to Supabase instead of rebuilding and
     * redeploying the app. Views keep the bundled public/ copy as a fallback, so
     * nothing breaks if these objects are ever removed again.
     */
    protected function uploadBrandAssets(bool $dry): void
    {
        $disk = Media::disk();
        $force = (bool) $this->option('force');

        $this->info('Brand artwork (logo mark, favicon)');

        foreach (Media::BRAND_FILES as $name => $localRelative) {
            $source = public_path($localRelative);
            $target = Media::brandPath($name);

            if (! File::isFile($source)) {
                $this->warn('  ! public/'.$localRelative.' is missing locally');

                continue;
            }

            try {
                if (! $force && $disk->exists($target)) {
                    $this->line('  = '.$target.' already stored');

                    continue;
                }
            } catch (Throwable $e) {
                $this->error('  ! '.$target.' → '.($e->getPrevious()?->getMessage() ?: $e->getMessage()));

                continue;
            }

            if ($dry) {
                $this->line('  + '.$target);

                continue;
            }

            try {
                // No mimetype option: the adapter derives it from the extension.
                if ($disk->put($target, File::get($source))) {
                    $this->line('  ↑ '.$target);
                } else {
                    $this->error('  ! '.$target.' could not be uploaded');
                }
            } catch (Throwable $e) {
                $this->error('  ! '.$target.' → '.$e->getMessage());
            }
        }

        $this->line('  served from: <info>'.$disk->url(Media::brandPath('logo-mark.png')).'</info>');
    }

    /**
     * Rewrite database values so every row resolves to a working media URL.
     */
    protected function normaliseUrls(bool $dry): void
    {
        $this->info('Normalising stored URLs');

        // file_path columns keep holding a path on purpose: views resolve them
        // through Media::url(), which understands both forms.
        $targets = [
            ['asset_files', 'url', 'Asset_file_ID'],
            ['requests', 'url', 'id'],
            ['users', 'profile_photo', 'id'],
        ];

        foreach ($targets as [$table, $column, $key]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $changed = 0;

            DB::table($table)
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->orderBy($key)
                ->chunkById(200, function ($rows) use ($table, $column, $key, $dry, &$changed) {
                    foreach ($rows as $row) {
                        $current = (string) $row->{$column};
                        $fixed = Media::url($current);

                        if ($fixed === null || $fixed === $current) {
                            continue;
                        }

                        $changed++;

                        if (! $dry) {
                            DB::table($table)->where($key, $row->{$key})->update([$column => $fixed]);
                        }
                    }
                }, $key);

            $this->line(sprintf('  %s.%s → %d row(s) %s', $table, $column, $changed, $dry ? 'to fix' : 'updated'));
        }

        // Asset QR codes are stored as relative paths; flatten any absolute URL
        // left over from an older upload script.
        $flattened = 0;

        DB::table('assets')
            ->whereNotNull('qr_code_path')
            ->where('qr_code_path', 'like', 'http%')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($dry, &$flattened) {
                foreach ($rows as $row) {
                    $fixed = Media::normalizePath((string) $row->qr_code_path);

                    if ($fixed === '' || $fixed === $row->qr_code_path) {
                        continue;
                    }

                    $flattened++;

                    if (! $dry) {
                        DB::table('assets')->where('id', $row->id)->update(['qr_code_path' => $fixed]);
                    }
                }
            });

        $this->line(sprintf('  assets.qr_code_path → %d row(s) %s', $flattened, $dry ? 'to fix' : 'flattened'));
    }

    /**
     * Generate and store QR images for assets that do not have one yet.
     */
    protected function generateMissingQr(bool $dry): void
    {
        $assets = DB::table('assets')
            ->select('id', 'Asset_code', 'qr_code_path')
            ->whereNotNull('Asset_code')
            ->where('Asset_code', '!=', '')
            ->orderBy('id')
            ->get()
            ->filter(fn ($asset) => empty($asset->qr_code_path) || ! Media::exists($asset->qr_code_path));

        $this->info('Assets without a stored QR image: '.$assets->count());

        if ($assets->isEmpty()) {
            return;
        }

        if ($dry) {
            foreach ($assets as $asset) {
                $this->line('  + '.$asset->Asset_code);
            }

            return;
        }

        $bar = $this->output->createProgressBar($assets->count());
        $bar->start();

        $generated = 0;

        foreach ($assets as $asset) {
            $path = Media::generateQr((string) $asset->Asset_code);

            if ($path !== null) {
                DB::table('assets')->where('id', $asset->id)->update([
                    'qr_code_path' => $path,
                    'updated_at' => now(),
                ]);

                $generated++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->line(sprintf('  generated %d of %d', $generated, $assets->count()));
    }
}
