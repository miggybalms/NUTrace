<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateImageUrlsToSupabase extends Command
{
    protected $signature = 'images:update-urls';
    protected $description = 'Update image URLs to point to Supabase Storage';

    public function handle()
    {
        $baseUrl = rtrim(env('SUPABASE_URL'), '/') . '/storage/v1/object/public/assets/';

        $this->info('Updating asset_files...');
        $updated1 = DB::table('asset_files')
            ->where('url', 'like', '/storage/%')
            ->update([
                'url' => DB::raw("REPLACE(url, '/storage/', '{$baseUrl}')")
            ]);
        $this->info("Updated {$updated1} rows in asset_files");

        $this->info('Updating requests...');
        $updated2 = DB::table('requests')
            ->where('url', 'like', '/storage/%')
            ->update([
                'url' => DB::raw("REPLACE(url, '/storage/', '{$baseUrl}')")
            ]);
        $this->info("Updated {$updated2} rows in requests");

        $this->info('Updating users profile photos...');
        $updated3 = DB::table('users')
            ->whereNotNull('profile_photo')
            ->where('profile_photo', 'not like', 'http%')
            ->update([
                'profile_photo' => DB::raw("CONCAT('{$baseUrl}', profile_photo)")
            ]);
        $this->info("Updated {$updated3} rows in users");

        $this->info('All URLs updated successfully!');
    }
}