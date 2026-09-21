<?php

namespace App\Support\Filesystem;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToCheckExistence;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToWriteFile;
use League\MimeTypeDetection\ExtensionMimeTypeDetector;
use League\Flysystem\Visibility;
use Throwable;

/**
 * Flysystem adapter for Supabase Storage.
 *
 * Talks to Supabase's Storage REST API with the service-role key the project
 * already keeps in .env, so no S3 credentials or extra composer packages are
 * needed. Objects are written into a single bucket (default: "assets"), with
 * the folder structure preserved ("assets/...", "profile_photos/...", ...).
 */
class SupabaseAdapter implements FilesystemAdapter
{
    protected string $endpoint;

    protected string $publicUrl;

    public function __construct(
        protected string $bucket,
        string $endpoint,
        protected string $key,
        ?string $publicUrl = null,
        protected int $timeout = 30,
    ) {
        $this->endpoint  = rtrim($endpoint, '/');
        $this->publicUrl = rtrim(
            $publicUrl ?: $this->endpoint.'/storage/v1/object/public/'.$this->bucket,
            '/'
        );
    }

    /**
     * Public URL of a stored object. Laravel's filesystem adapter calls this
     * for Storage::disk('...')->url($path).
     */
    public function getUrl(string $path): string
    {
        return $this->publicUrl.'/'.$this->encode($path);
    }

    public function fileExists(string $path): bool
    {
        try {
            return $this->objectInfo($path) !== null;
        } catch (Throwable $e) {
            throw UnableToCheckExistence::forLocation($path, $e);
        }
    }

    public function directoryExists(string $path): bool
    {
        try {
            foreach ($this->listContents($path, false) as $item) {
                return true;
            }

            return false;
        } catch (Throwable $e) {
            throw UnableToCheckExistence::forLocation($path, $e);
        }
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $this->upload($path, $contents, $this->mimeTypeFor($path, $config));
    }

    /**
     * @param  resource  $contents
     */
    public function writeStream(string $path, $contents, Config $config): void
    {
        $body = stream_get_contents($contents);

        if ($body === false) {
            throw UnableToWriteFile::atLocation($path, 'The stream could not be read.');
        }

        $this->upload($path, $body, $this->mimeTypeFor($path, $config));
    }

    public function read(string $path): string
    {
        try {
            $response = $this->client()->get($this->objectUrl($path));
        } catch (Throwable $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }

        if (! $response->successful()) {
            throw UnableToReadFile::fromLocation($path, $this->errorReason($response->status(), $response->body()));
        }

        return $response->body();
    }

    /**
     * @return resource
     */
    public function readStream(string $path)
    {
        $body   = $this->read($path);
        $stream = fopen('php://temp', 'r+b');

        if ($stream === false) {
            throw UnableToReadFile::fromLocation($path, 'Unable to open a temporary stream.');
        }

        fwrite($stream, $body);
        rewind($stream);

        return $stream;
    }

    public function delete(string $path): void
    {
        try {
            $response = $this->client()->delete($this->objectUrl($path));
        } catch (Throwable $e) {
            throw UnableToDeleteFile::atLocation($path, $e->getMessage(), $e);
        }

        // Supabase answers 400/404 when the object was already gone; that is not
        // an error for Flysystem's delete semantics.
        if (! $response->successful() && ! in_array($response->status(), [400, 404], true)) {
            throw UnableToDeleteFile::atLocation($path, $this->errorReason($response->status(), $response->body()));
        }
    }

    public function deleteDirectory(string $path): void
    {
        $prefix = trim($path, '/');

        try {
            foreach ($this->allObjects($prefix) as $object) {
                if (($object['id'] ?? null) === null) {
                    continue; // folder marker
                }

                $this->delete($prefix.'/'.$object['name']);
            }
        } catch (Throwable $e) {
            throw UnableToDeleteDirectory::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function createDirectory(string $path, Config $config): void
    {
        // Supabase Storage has no real directories: prefixes appear with the
        // first object written under them.
    }

    public function setVisibility(string $path, string $visibility): void
    {
        // Visibility is controlled by the bucket, not per object.
    }

    public function visibility(string $path): FileAttributes
    {
        return new FileAttributes($path, null, Visibility::PUBLIC);
    }

    public function mimeType(string $path): FileAttributes
    {
        $info = $this->metadataFor($path);

        return new FileAttributes($path, null, null, null, $info['content_type'] ?? null);
    }

    public function lastModified(string $path): FileAttributes
    {
        $info = $this->metadataFor($path);
        $timestamp = isset($info['last_modified']) ? strtotime((string) $info['last_modified']) : false;

        return new FileAttributes($path, null, null, $timestamp === false ? null : $timestamp);
    }

    public function fileSize(string $path): FileAttributes
    {
        $info = $this->metadataFor($path);

        return new FileAttributes($path, isset($info['size']) ? (int) $info['size'] : null);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        $prefix = trim($path, '/');
        $prefix = $prefix === '' ? '' : $prefix.'/';
        $seen   = [];

        foreach ($this->allObjects($prefix) as $object) {
            $name = trim((string) ($object['name'] ?? ''), '/');

            if ($name === '') {
                continue;
            }

            $full = $prefix.$name;

            if (($object['id'] ?? null) === null) {
                // Folder marker. Supabase only lists one level at a time, so
                // recurse ourselves when a deep listing was requested.
                if (isset($seen[$full])) {
                    continue;
                }

                $seen[$full] = true;

                if ($deep) {
                    yield new DirectoryAttributes($full);

                    foreach ($this->listContents($full, true) as $child) {
                        yield $child;
                    }
                } elseif (! str_contains($name, '/')) {
                    yield new DirectoryAttributes($full);
                }

                continue;
            }

            if (! $deep && str_contains($name, '/')) {
                continue;
            }

            $metadata = is_array($object['metadata'] ?? null) ? $object['metadata'] : [];

            yield new FileAttributes(
                $full,
                isset($metadata['size']) ? (int) $metadata['size'] : null,
                null,
                isset($object['updated_at']) ? (int) strtotime((string) $object['updated_at']) : null,
                $metadata['mimetype'] ?? null,
            );
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $this->copy($source, $destination, $config);
        $this->delete($source);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            $response = $this->client()->post($this->endpoint.'/storage/v1/object/copy', [
                'bucketId'       => $this->bucket,
                'sourceKey'      => $this->encode($source),
                'destinationKey' => $this->encode($destination),
            ]);
        } catch (Throwable $e) {
            throw UnableToWriteFile::atLocation($destination, $e->getMessage(), $e);
        }

        if (! $response->successful()) {
            throw UnableToWriteFile::atLocation(
                $destination,
                $this->errorReason($response->status(), $response->body())
            );
        }
    }

    /* ------------------------------------------------------------------ */
    /* Internals                                                          */
    /* ------------------------------------------------------------------ */

    protected function client(): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer '.$this->key,
            'apikey'        => $this->key,
        ])
            ->timeout($this->timeout)
            // Storage occasionally drops requests when many files are being
            // checked or uploaded in a row: retry only the transient failures,
            // never a genuine 4xx (a missing object, a bad path).
            ->retry(3, 250, function (Throwable $e) {
                if ($e instanceof RequestException && $e->response !== null) {
                    $status = $e->response->status();

                    return $status === 408 || $status === 429 || $status >= 500;
                }

                return true; // connection / DNS / timeout problems
            }, throw: false);
    }

    protected function objectUrl(string $path): string
    {
        return $this->endpoint.'/storage/v1/object/'.$this->bucket.'/'.$this->encode($path);
    }

    protected function encode(string $path): string
    {
        $segments = array_map('rawurlencode', explode('/', ltrim($path, '/')));

        return implode('/', $segments);
    }

    protected function upload(string $path, string $body, string $mimeType): void
    {
        try {
            $response = $this->client()
                ->withHeaders([
                    'x-upsert'     => 'true',
                    'Content-Type' => $mimeType,
                ])
                ->withBody($body, $mimeType)
                ->post($this->objectUrl($path));
        } catch (Throwable $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }

        if (! $response->successful()) {
            throw UnableToWriteFile::atLocation($path, $this->errorReason($response->status(), $response->body()));
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function objectInfo(string $path): ?array
    {
        $response = $this->client()
            ->get($this->endpoint.'/storage/v1/object/info/'.$this->bucket.'/'.$this->encode($path));

        if (! $response->successful()) {
            return null;
        }

        $info = $response->json();

        return is_array($info) ? $info : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function metadataFor(string $path): array
    {
        $info = $this->objectInfo($path);

        if ($info === null) {
            throw UnableToRetrieveMetadata::create(
                $path,
                'metadata',
                'Object not found in bucket "'.$this->bucket.'".'
            );
        }

        return $info;
    }

    /**
     * Every object under a prefix, following Supabase's 100-row pages.
     *
     * @return iterable<int, array<string, mixed>>
     */
    protected function allObjects(string $prefix): iterable
    {
        $offset = 0;
        $limit  = 100;

        while (true) {
            try {
                $response = $this->client()->post($this->endpoint.'/storage/v1/object/list/'.$this->bucket, [
                    'prefix' => $prefix,
                    'limit'  => $limit,
                    'offset' => $offset,
                ]);
            } catch (Throwable $e) {
                throw UnableToCheckExistence::forLocation($prefix, $e);
            }

            if (! $response->successful()) {
                throw UnableToCheckExistence::forLocation(
                    $prefix,
                    $this->errorReason($response->status(), $response->body())
                );
            }

            $items = $response->json();

            if (! is_array($items) || $items === []) {
                return;
            }

            foreach ($items as $item) {
                if (is_array($item)) {
                    yield $item;
                }
            }

            if (count($items) < $limit) {
                return;
            }

            $offset += $limit;
        }
    }

    protected function mimeTypeFor(string $path, Config $config): string
    {
        $configured = $config->get('mimetype');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return (new ExtensionMimeTypeDetector())->detectMimeTypeFromPath($path)
            ?: 'application/octet-stream';
    }

    protected function errorReason(int $status, string $body): string
    {
        $decoded = json_decode($body, true);
        $message = is_array($decoded)
            ? ($decoded['message'] ?? $decoded['error'] ?? null)
            : null;

        return trim('HTTP '.$status.' '.($message ?: $body));
    }
}
