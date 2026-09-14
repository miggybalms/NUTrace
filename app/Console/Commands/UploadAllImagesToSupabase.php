<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class UploadAllImagesToSupabase extends Command
{
    protected $signature = 'images:upload-to-supabase';
    protected $description = 'Upload assets, profile_photos and request_files to Supabase Storage';

    public function handle()
    {
        $supabaseUrl = rtrim(env('SUPABASE_URL'), '/');
        $supabaseKey = env('SUPABASE_SERVICE_ROLE_KEY') ?: env('SUPABASE_ANON_KEY');

        if (!$supabaseUrl || !$supabaseKey) {
            $this->error('Missing SUPABASE_URL or key in .env');
            return 1;
        }

        $folders = ['assets', 'profile_photos', 'request_files'];

        foreach ($folders as $folder) {
            $localPath = storage_path("app/public/{$folder}");

            if (!File::exists($localPath)) {
                $this->warn("Folder not found: {$folder}");
                continue;
            }

            $files = File::allFiles($localPath);
            $this->info("Uploading {$folder} (" . count($files) . " files)...");

            $bar = $this->output->createProgressBar(count($files));
            $bar->start();

            foreach ($files as $file) {
                $relativePath = $folder . '/' . $file->getRelativePathname();
                $fullPath = $file->getPathname();
                $mimeType = mime_content_type($fullPath) ?: 'application/octet-stream';

                $uploadUrl = "{$supabaseUrl}/storage/v1/object/assets/{$relativePath}";

                try {
                    $response = Http::timeout(30)
                        ->withHeaders([
                            'Authorization' => 'Bearer ' . $supabaseKey,
                            'apikey'        => $supabaseKey,
                            'Content-Type'  => $mimeType,
                        ])
                        ->withBody(file_get_contents($fullPath), $mimeType)
                        ->put($uploadUrl);

                    if (!$response->successful()) {
                        $this->newLine();
                        $this->error("Failed: {$relativePath} → " . $response->status() . ' ' . $response->body());
                    }
                } catch (\Exception $e) {
                    $this->newLine();
                    $this->error("Error: {$relativePath} → " . $e->getMessage());
                }

                // Small delay to avoid rate limiting / connection issues
                usleep(150000); // 0.15 second

                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);
        }

        $this->info('All uploads finished!');
        return 0;
    }
}