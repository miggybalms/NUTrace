<?php

namespace App\Console\Commands;

use App\Support\QrSigner;
use Illuminate\Console\Command;

/**
 * Mints a QR signing key and prints the two environment variables that put it
 * to work.
 *
 *   php artisan qr:keygen                        Ed25519 key, id V1
 *   php artisan qr:keygen --id=V2 --algo=ecdsa-p256
 *   php artisan qr:keygen --payload              the QR_PAYLOAD_KEY that seals
 *                                                the asset code into a sticker
 *
 * The secret half goes into the deployment platform's variables and nowhere
 * else - not into the repository, not into a chat, not into a ticket. The
 * public half is printed too, because that is the line a retired key is
 * replaced with: it can still verify the stickers already printed with that
 * key, but can never sign a new one.
 */
class GenerateQrSigningKey extends Command
{
    protected $signature = 'qr:keygen
        {--id=V1 : Key id stored inside every payload (A-Z and 0-9, up to 16 characters)}
        {--algo=ed25519 : ed25519 (libsodium) or ecdsa-p256 (OpenSSL)}
        {--payload : Print a QR_PAYLOAD_KEY instead of a signing key}';

    protected $description = 'Generate a QR signing key (or a payload key) and print the environment variables for it';

    public function handle(): int
    {
        if ($this->option('payload')) {
            return $this->printPayloadKey();
        }

        $id = (string) $this->option('id');
        $algorithm = strtolower((string) $this->option('algo'));

        try {
            $key = QrSigner::generate($algorithm, $id);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            if (! QrSigner::supports(QrSigner::ED25519)) {
                $this->line('libsodium (ext-sodium) is not loaded, so Ed25519 is unavailable here. '
                    .'Trying anyway is harmless - ecdsa-p256 works on every PHP build.');
            }

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('Algorithm: <info>' . $key['algorithm'] . '</info>   Key id: <info>' . $key['id'] . '</info>');
        $this->newLine();

        $this->comment('Put these two variables in the deployment platform\'s secret variables, then run:');
        $this->line('  php artisan config:clear');
        $this->newLine();

        $this->line($key['env_keys']);
        $this->line($key['env_active']);
        $this->newLine();

        $this->comment('To rotate later, keep the old key verifying-only by replacing it with this line:');
        $this->line('  ' . $key['id'] . ':' . QrSigner::publicAlgorithm($key['algorithm']) . ':' . $key['public']);
        $this->newLine();

        $this->warn('The secret above is the only copy: store it in the platform\'s variables now. '
            .'Never commit it, paste it in chat, or reuse it for anything else.');

        return self::SUCCESS;
    }

    /**
     * The key that seals asset codes into sticker bodies. It is not a signing
     * key: it only hides the code, and every install that has to read the same
     * stickers must share it.
     */
    private function printPayloadKey(): int
    {
        $key = 'base64:' . base64_encode(random_bytes(32));

        $this->newLine();
        $this->comment('Put this in the deployment platform\'s variables, and in every install that must read the same stickers:');
        $this->line('QR_PAYLOAD_KEY="' . $key . '"');
        $this->newLine();
        $this->line('Then run <info>php artisan config:clear</info>, and reprint stickers with '
            .'<info>php artisan qr:resign</info> so they carry the sealed format.');
        $this->newLine();
        $this->warn('Keep it: stickers sealed with this key cannot be opened after it is lost or changed.');

        return self::SUCCESS;
    }
}
