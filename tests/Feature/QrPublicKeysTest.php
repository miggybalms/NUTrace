<?php

namespace Tests\Feature;

use App\Support\QrPayload;
use App\Support\QrSigner;
use Tests\TestCase;

/**
 * The verifying half of the QR keyring, as served to a client that has no PHP
 * session (the mobile app).
 *
 * The contract this file locks down:
 *
 *   1. a guest can read it - public keys are not a secret, and the phone has no
 *      Laravel session to authenticate with
 *   2. it carries only public material: the two shapes it emits (an Ed25519
 *      public key, an uncompressed P-256 point) are enough to verify a genuine
 *      sticker and useless for minting one
 *   3. a signing key is reduced to its public half, so the same deployment does
 *      not have to duplicate its keyring into the app
 *   4. a deployment with no keyring says `configured: false` instead of letting
 *      a client conclude anything about a signature it cannot check
 */
class QrPublicKeysTest extends TestCase
{
    protected function tearDown(): void
    {
        QrSigner::flush();

        parent::tearDown();
    }

    /** Put a keyring in place exactly as the deployment's env var would. */
    private function useKeyring(string $entries, ?string $activeKeyId = null): void
    {
        config([
            'qr.algorithm' => 'auto',
            'qr.keys' => $entries,
            'qr.active_key_id' => $activeKeyId,
        ]);

        QrSigner::flush();
    }

    private function fetchKeyring(): array
    {
        return $this->getJson('/qr/public-keys')->assertOk()->json();
    }

    /** Rebuild an SPKI PEM around a raw `04 || X || Y` point, so an OpenSSL
     *  verifier can be pointed at what the endpoint actually emitted. */
    private function p256PublicKeyFromPoint(string $point): string
    {
        $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $point;

        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($der), 64, "\n")
            . '-----END PUBLIC KEY-----';
    }

    public function test_a_guest_can_read_the_public_keyring(): void
    {
        $key = QrSigner::generate(QrSigner::ED25519, 'V1');
        $this->useKeyring('V1:' . QrSigner::ED25519 . ':' . $key['secret'], 'V1');

        // No actingAs: the mobile app has no session to present.
        $body = $this->fetchKeyring();

        $this->assertTrue($body['configured']);
        $this->assertSame(['V1'], array_keys($body['keys']));
        $this->assertSame('ed25519', $body['keys']['V1']['algorithm']);
        $this->assertSame($key['public'], $body['keys']['V1']['public']);
    }

    public function test_a_signing_key_is_reduced_to_its_public_half_and_the_secret_never_travels(): void
    {
        $key = QrSigner::generate(QrSigner::ED25519, 'V1');
        $this->useKeyring('V1:' . QrSigner::ED25519 . ':' . $key['secret'], 'V1');

        $response = $this->getJson('/qr/public-keys')->assertOk();

        $this->assertStringNotContainsString($key['secret'], $response->getContent());
        // The seed is 32 bytes; the public half is a different 32-byte key.
        $this->assertNotSame($key['secret'], $response->json('keys.V1.public'));
    }

    public function test_an_ed25519_public_key_from_the_endpoint_verifies_a_real_payload(): void
    {
        $key = QrSigner::generate(QrSigner::ED25519, 'V1');
        $this->useKeyring('V1:' . QrSigner::ED25519 . ':' . $key['secret'], 'V1');

        $signed = QrSigner::sign(QrPayload::message('AST-0001', 'V1'));
        $this->assertIsArray($signed);

        $public = base64_decode($this->fetchKeyring()['keys']['V1']['public'], true);
        $this->assertIsString($public);

        $this->assertTrue(sodium_crypto_sign_verify_detached(
            $signed['signature'],
            QrPayload::message('AST-0001', 'V1'),
            $public
        ));
    }

    public function test_a_p256_point_from_the_endpoint_verifies_an_openssl_signature(): void
    {
        $key = QrSigner::generate(QrSigner::ECDSA_P256, 'E1');
        $this->useKeyring('E1:' . QrSigner::ECDSA_P256 . ':' . $key['secret'], 'E1');

        $message = QrPayload::message('AST-0002', 'E1');
        $signed = QrSigner::sign($message);
        $this->assertIsArray($signed);

        $entry = $this->fetchKeyring()['keys']['E1'];
        $this->assertSame('ecdsa-p256', $entry['algorithm']);

        $point = base64_decode($entry['public'], true);
        $this->assertIsString($point);
        $this->assertSame(65, strlen($point));
        $this->assertSame("\x04", $point[0]);

        $this->assertSame(1, openssl_verify(
            $message,
            $signed['signature'],
            $this->p256PublicKeyFromPoint($point),
            OPENSSL_ALGO_SHA256
        ));
    }

    public function test_a_retired_public_key_is_still_published_for_verification(): void
    {
        $active = QrSigner::generate(QrSigner::ED25519, 'V2');
        $retired = QrSigner::generate(QrSigner::ED25519, 'V1');

        $this->useKeyring(implode(',', [
            'V2:' . QrSigner::ED25519 . ':' . $active['secret'],
            'V1:' . QrSigner::ED25519_PUBLIC . ':' . $retired['public'],
        ]), 'V2');

        $keys = $this->fetchKeyring()['keys'];

        $this->assertSame(['V2', 'V1'], array_keys($keys));
        $this->assertSame($retired['public'], $keys['V1']['public']);
        $this->assertSame($active['public'], $keys['V2']['public']);
    }

    public function test_an_empty_keyring_is_reported_as_unconfigured(): void
    {
        $this->useKeyring('');

        $body = $this->fetchKeyring();

        $this->assertFalse($body['configured']);
        $this->assertSame([], $body['keys']);
    }
}
