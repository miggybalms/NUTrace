<?php

namespace App\Console\Commands;

use App\Support\Filesystem\SupabaseAdapter;
use App\Support\Media;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use League\Flysystem\Config;
use Throwable;

/**
 * Answers "why does the deployed site show No image?" in one run.
 *
 * A rejected upload is silent by design: the disk returns false instead of
 * throwing, the asset is still saved, and the UI falls back to "No image". So
 * the only way to tell a missing key from a bucket policy from an unreachable
 * endpoint is to write a real object and print what the storage API answered.
 *
 *   php artisan media:doctor
 *
 * Safe to run on production: it writes one tiny object under _doctor/ and
 * removes it again. No application data is touched.
 */
class MediaDoctor extends Command
{
    protected $signature = 'media:doctor';

    protected $description = 'Verify that uploads reach the configured media disk and report why they would not';

    public function handle(): int
    {
        $disk   = Media::DISK;
        $driver = (string) config("filesystems.disks.{$disk}.driver");
        $bucket = (string) config("filesystems.disks.{$disk}.bucket");
        $endpoint = (string) config("filesystems.disks.{$disk}.endpoint");
        $key    = (string) config("filesystems.disks.{$disk}.key");

        $this->newLine();
        $this->line('<info>Media disk</info> "'.$disk.'"');
        $this->line('  driver    '.$driver);
        $this->line('  bucket    '.($bucket !== '' ? $bucket : '(none)'));
        $this->line('  endpoint  '.($endpoint !== '' ? $endpoint : '(none)'));
        $this->line('  APP_URL   '.env('APP_URL', '(unset)'));
        $this->line('  config    '.(app()->configurationIsCached() ? 'cached — run `php artisan config:clear` after changing variables' : 'not cached'));

        $keySource = $this->keySource();
        $this->line('  key       '.$keySource.' — '.$this->describeKey($key));

        if ($driver !== 'supabase') {
            $this->newLine();
            $this->warn('The media disk is the LOCAL disk, not Supabase Storage.');
            $this->line('Files written there are deleted on the next deploy, so every visitor');
            $this->line('sees "No image". Set SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY on the');
            $this->line('deployment (Railway → Variables), then run: php artisan config:clear');
            $this->reportAssetsWithoutImage();

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('<info>Round trip</info>');

        $adapter = new SupabaseAdapter(
            bucket: $bucket,
            endpoint: $endpoint,
            key: $key,
            publicUrl: config("filesystems.disks.{$disk}.url"),
            timeout: (int) config("filesystems.disks.{$disk}.timeout", 30),
        );

        $path = '_doctor/probe-'.Str::lower(Str::random(10)).'.txt';
        $body = 'nutrace media doctor '.now()->toIso8601String();

        $writeError = null;

        $this->output->write('  1. writing to the bucket .......... ');

        try {
            $adapter->write($path, $body, new Config());
            $this->info('ok');
        } catch (Throwable $e) {
            $writeError = $e->getMessage();
            $this->error('FAILED');
        }

        if ($writeError !== null) {
            $this->newLine();
            $this->error('  '.$writeError);
            $this->newLine();
            $this->line($this->explain($writeError));
            $this->reportAssetsWithoutImage();

            return self::FAILURE;
        }

        $this->output->write('  2. reading it back via the API .... ');

        try {
            $read = $adapter->read($path);
            $this->info($read === $body ? 'ok' : 'ok (content differed)');
        } catch (Throwable $e) {
            $this->error('FAILED — '.$e->getMessage());
        }

        $this->output->write('  3. fetching its public URL ........ ');
        $url = $adapter->getUrl($path);

        try {
            $status = Http::timeout(20)->get($url)->status();
            $status === 200
                ? $this->info('ok (HTTP 200)')
                : $this->warn('HTTP '.$status.' — the bucket is probably not PUBLIC');
        } catch (Throwable $e) {
            $this->warn('could not be reached — '.$e->getMessage());
        }

        $this->output->write('  4. deleting the probe ............. ');

        try {
            $adapter->delete($path);
            $this->info('ok');
        } catch (Throwable $e) {
            $this->warn('FAILED — '.$e->getMessage().' (remove _doctor/probe-*.txt by hand)');
        }

        $this->newLine();
        $this->info('Verdict: uploads reach Supabase Storage. New registrations will keep their photos.');
        $this->line('Browser URL pattern: '.rtrim((string) config("filesystems.disks.{$disk}.url"), '/').'/assets/<file>');

        $this->reportAssetsWithoutImage();

        return self::SUCCESS;
    }

    /**
     * Which variable the active key came from, without ever printing the key.
     */
    protected function keySource(): string
    {
        $used = (string) config('filesystems.disks.'.Media::DISK.'.key');
        $service = (string) env('SUPABASE_SERVICE_ROLE_KEY');
        $anon = (string) env('SUPABASE_ANON_KEY');

        if ($used === '') {
            return 'none configured';
        }

        if ($service !== '' && $used === $service) {
            return 'SUPABASE_SERVICE_ROLE_KEY';
        }

        if ($anon !== '' && $used === $anon) {
            return 'SUPABASE_ANON_KEY';
        }

        return 'filesystems.disks.public.key (config)';
    }

    /**
     * Role carried by the key: only a service-role/secret key may write to a
     * bucket whose policies were never opened to the anon role.
     */
    protected function describeKey(string $key): string
    {
        if ($key === '') {
            return 'missing';
        }

        if (str_starts_with($key, 'sb_secret_')) {
            return 'secret key (server side, bypasses row level security)';
        }

        if (str_starts_with($key, 'sb_publishable_')) {
            return 'publishable key (client side, subject to row level security)';
        }

        $parts = explode('.', $key);

        if (count($parts) === 3) {
            $payload = strtr($parts[1], '-_', '+/');
            $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);

            $claims = json_decode((string) base64_decode($payload, true), true);

            if (is_array($claims)) {
                return 'JWT role "'.($claims['role'] ?? 'unknown').'"'
                    .' for project "'.($claims['ref'] ?? 'unknown').'"';
            }
        }

        return 'unrecognised format';
    }

    protected function explain(string $error): string
    {
        if (str_contains($error, 'row-level security') || str_contains($error, '403')) {
            return 'The storage API refused the write. A bucket that is only set to PUBLIC allows
anonymous READS, never writes: the anon key is subject to row level security.
Add SUPABASE_SERVICE_ROLE_KEY (Supabase → Project Settings → API → service_role
secret) to the deployment and run `php artisan config:clear`, or add an INSERT
policy for the bucket under Storage → Policies.';
        }

        if (str_contains($error, 'Bucket not found') || str_contains($error, '404')) {
            return 'The bucket named in SUPABASE_BUCKET does not exist. Create it in
Supabase → Storage (name it exactly "assets", mark it PUBLIC).';
        }

        if (str_contains($error, '401') || str_contains($error, 'Invalid') || str_contains($error, 'JWT')) {
            return 'The storage API did not accept the key. SUPABASE_URL and the key must
belong to the SAME project (ref must match in both).';
        }

        if (str_contains($error, 'exceeded') || str_contains($error, 'too large')) {
            return 'The bucket rejects files above its size limit. Raise the limit in
Supabase → Storage → the bucket → Edit bucket → File size limit.';
        }

        return 'Copy the message above: it is the storage API\'s own explanation.';
    }

    /**
     * The user-visible symptom: assets with no row in asset_files, which the
     * UI renders as "No image".
     */
    protected function reportAssetsWithoutImage(): void
    {
        try {
            if (! DB::getSchemaBuilder()->hasTable('asset_files')) {
                return;
            }

            $without = DB::table('assets')
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('asset_files')
                        ->whereColumn('asset_files.Asset_id', 'assets.id');
                })
                ->count();

            $total = DB::table('assets')->count();

            $this->newLine();
            $this->line('<info>Assets without a photo</info>');
            $this->line('  '.$without.' of '.$total.' assets have no row in asset_files, so they show "No image".');

            if ($without > 0) {
                $this->line('  Re-upload a photo for them from the asset page, or re-register them.');
            }
        } catch (Throwable $e) {
            $this->warn('Could not read the database: '.$e->getMessage());
        }
    }
}
