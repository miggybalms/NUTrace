<?php

namespace App\Console\Commands;

use App\Support\AssetQr;
use App\Support\QrPayload;
use App\Support\QrSigner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Re-mints stored sticker images as signed payloads.
 *
 *   php artisan qr:resign --dry-run   say which stickers are still unsigned
 *   php artisan qr:resign             re-mint just those
 *   php artisan qr:resign --all       re-mint every asset's sticker image
 *
 * Nothing here changes what a sticker printed months ago does: those carry a
 * plain asset code, they keep resolving, and every scan of them is labelled
 * "unsigned". Re-minting only refreshes the *stored* image (the one an asset
 * page or a label sheet draws), so the office knows exactly which assets need
 * a reprint when that is convenient - a controlled migration instead of a
 * silent one.
 */
class ResignAssetQrCodes extends Command
{
    protected $signature = 'qr:resign
        {--all : Re-mint every asset sticker, not only the unsigned ones}
        {--limit=0 : Stop after this many assets (0 = every asset that needs it)}
        {--dry-run : Report what would be re-minted without writing anything}';

    protected $description = 'Re-mint stored QR sticker images as signed payloads, and report which printed stickers are still unsigned';

    public function handle(): int
    {
        if (! QrSigner::isConfigured()) {
            $this->error('No QR signing key is configured, so there is nothing to sign with.');
            $this->line('Run <info>php artisan qr:keygen</info>, put the printed variables in the deployment '
                .'platform (or .env), then <info>php artisan config:clear</info>.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $all = (bool) $this->option('all');
        $limit = max(0, (int) $this->option('limit'));

        $this->line('Signing with key <info>'.QrSigner::activeKeyId().'</info> ('.QrSigner::algorithm().')');

        $rows = DB::table('assets')
            ->whereNotNull('Asset_code')
            ->where('Asset_code', '!=', '')
            ->orderBy('id')
            ->get(['id', 'Asset_code', 'qr_code_path', 'qr_payload', 'qr_revoked_at']);

        $reasons = ['unsigned' => [], 'retired' => [], 'missing' => []];
        $revoked = 0;

        foreach ($rows as $row) {
            if (! empty($row->qr_revoked_at)) {
                $revoked++;
            }

            $reason = $this->reasonToReissue($row);

            if ($reason !== null) {
                $reasons[$reason][] = $row->Asset_code;
            }
        }

        $targets = $all
            ? $rows
            : $rows->filter(fn ($row) => $this->reasonToReissue($row) !== null);

        if ($limit > 0) {
            $targets = $targets->take($limit);
        }

        $this->newLine();
        $this->line('Assets with a code: <info>'.$rows->count().'</info>   ·   needing a fresh signed sticker: <info>'.$targets->count().'</info>');

        foreach ([
            'unsigned' => 'no signed payload recorded (printed before signing)',
            'retired' => 'signed with a key this install no longer holds',
            'missing' => 'no stored sticker image',
        ] as $key => $description) {
            if (! empty($reasons[$key])) {
                $this->line('  · '.count($reasons[$key]).' '.$description);
            }
        }

        if ($revoked > 0) {
            $this->line('  · '.$revoked.' sticker(s) revoked by the office; they stay revoked either way');
        }

        if (! empty($reasons['unsigned'])) {
            $this->newLine();
            $this->comment('Printed stickers that still carry a plain code, and will keep working as "unsigned":');
            $this->line('  '.implode(', ', array_slice($reasons['unsigned'], 0, 12))
                .(count($reasons['unsigned']) > 12 ? ' … (+'.(count($reasons['unsigned']) - 12).' more)' : ''));
        }

        if ($targets->isEmpty()) {
            $this->newLine();
            $this->info('Every stored sticker already carries a signed payload.');

            return self::SUCCESS;
        }

        if ($dry) {
            $this->newLine();
            $this->warn('Dry run: nothing was written.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($targets->count());
        $bar->start();

        $minted = 0;
        $failed = 0;

        foreach ($targets as $row) {
            $path = AssetQr::saveSticker((int) $row->id);

            $path === null ? $failed++ : $minted++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->line(sprintf('  re-minted %d of %d', $minted, $targets->count()));

        if ($failed > 0) {
            $this->warn('  '.$failed.' could not be produced; the asset keeps its previous sticker image.');
        }

        $this->comment('Printed stickers in the field are unchanged - reprint from the label sheet when convenient.');

        return self::SUCCESS;
    }

    /**
     * Why this asset's stored sticker should be re-minted, or null when it is
     * already a payload signed by a key this install can verify.
     */
    private function reasonToReissue(object $row): ?string
    {
        $path = trim((string) ($row->qr_code_path ?? ''));
        $payload = trim((string) ($row->qr_payload ?? ''));

        if ($path === '') {
            return 'missing';
        }

        if ($payload === '') {
            return 'unsigned';
        }

        $parsed = QrPayload::parse($payload);

        if ($parsed['type'] !== 'signed') {
            return 'unsigned';
        }

        if (! QrSigner::known((string) $parsed['key_id'])) {
            return 'retired';
        }

        return null;
    }
}
