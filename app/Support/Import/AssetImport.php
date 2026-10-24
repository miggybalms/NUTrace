<?php

namespace App\Support\Import;

use App\Models\Asset;
use App\Models\User;
use App\Support\AssetCode;
use App\Support\AssetQr;
use App\Support\Csv;
use Carbon\Carbon;
use DateTime;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Bringing the office's existing Fixed Asset Registry into NU Trace.
 *
 * Three identifiers must never be confused, because the whole duplicate story
 * depends on them:
 *
 *   - `assets.id`         NU Trace's own row id
 *   - `assets.Asset_code` the code that is printed on the sticker
 *   - `assets.property_id_no`  the client's PROPERTY ID NO, which is what the
 *                               Asset Management Office uses to recognise the
 *                               same physical asset again
 *
 * A row is checked against the property number first (that is the primary
 * duplicate key), with ID NO. only ever used as a secondary warning. Nothing is
 * written to `assets` until the office presses Import, existing property
 * numbers are skipped rather than overwritten, and the database's partial
 * unique index is the last word if two imports race.
 *
 * An asset may be imported with nobody accountable yet: the name on the
 * spreadsheet is preserved in `assets.accountable_name` and the asset stays
 * unassigned until that person has a real, active NU Trace account. No account
 * is ever invented for a name.
 *
 * The work is staged in `asset_import_rows` a window at a time, so a very large
 * workbook is read, reviewed and written without any single request having to
 * hold it.
 */
class AssetImport
{
    /* A row's outcome. */
    public const READY = 'ready';

    public const REVIEW = 'review';

    public const BLOCKED = 'blocked';

    public const EXISTS = 'exists';

    public const IMPORTED = 'imported';

    public const FAILED = 'failed';

    /* Where the accountable-person lookup ended up. */
    public const PERSON_ASSIGNED = 'assigned';

    public const PERSON_NO_ACCOUNT = 'no_account';

    public const PERSON_INACTIVE_ACCOUNT = 'inactive_account';

    public const PERSON_INACTIVE_EMPLOYEE = 'inactive_employee';

    public const PERSON_NO_EMPLOYEE = 'no_employee';

    public const PERSON_AMBIGUOUS = 'ambiguous';

    public const PERSON_UNASSIGNED = 'unassigned';

    /**
     * Fields an import maps to. `label` is what the page calls them and
     * `aliases` are headed variants accepted from a spreadsheet.
     *
     * @var array<string, array{label:string, aliases:array<int,string>}>
     */
    public const FIELDS = [
        'property_id_no' => [
            'label'   => 'Property ID No',
            'aliases' => ['property id no', 'property id number', 'property no', 'property number', 'property id'],
        ],
        'inventory_id_no' => [
            'label'   => 'ID No.',
            'aliases' => ['id no', 'id number', 'inventory id', 'inventory no', 'item no'],
        ],
        'po_no' => [
            'label'   => 'PO No',
            'aliases' => ['po no', 'po number', 'purchase order', 'purchase order no'],
        ],
        'name' => [
            'label'   => 'Description',
            'aliases' => ['description', 'asset description', 'item description', 'asset name', 'name', 'particulars'],
        ],
        'accusion_date' => [
            'label'   => 'Date Acquired',
            'aliases' => ['date acquired', 'date of acquisition', 'acquisition date', 'acquired', 'date'],
        ],
        'location' => [
            'label'   => 'Location',
            'aliases' => ['location', 'asset location', 'room', 'room office', 'place'],
        ],
        'department' => [
            'label'   => 'Department',
            'aliases' => ['department', 'office', 'unit', 'department office'],
        ],
        'category' => [
            'label'   => 'Type of Asset',
            'aliases' => ['type of asset', 'asset type', 'type', 'category', 'asset category'],
        ],
        'unit_cost' => [
            'label'   => 'Unit Cost',
            'aliases' => ['unit cost', 'cost', 'amount', 'value', 'purchase price', 'acquisition cost'],
        ],
        'accountable' => [
            'label'   => 'Accountable Person',
            'aliases' => ['accountable person', 'accountable', 'custodian', 'holder', 'person accountable'],
        ],
        'asset_code' => [
            'label'   => 'Asset Code',
            'aliases' => ['asset code', 'assetcode', 'code', 'tag number', 'property tag'],
        ],
    ];

    /** The categories the assets table accepts, verbatim. */
    public const CATEGORIES = [
        'Furnitures and Fixtures',
        'General and Office Equipment',
        'Info and Equipment',
        'laboratory Apparatus and equipment',
        'library books',
        'Motor vehicles',
        'P.E Equipment',
        'Low value Asset',
    ];

    /** How the office's own wording is read as one of those categories. */
    private const CATEGORY_ALIASES = [
        'Furnitures and Fixtures'          => ['furnitures and fixtures', 'furniture and fixtures', 'furnitures and fittings', 'furniture and fittings', 'furniture', 'fixtures'],
        'General and Office Equipment'     => ['general and office equipment', 'office equipment', 'general equipment', 'equipment'],
        'Info and Equipment'               => ['info and equipment', 'information and equipment', 'information technology equipment', 'it equipment', 'ict equipment', 'computer equipment'],
        'laboratory Apparatus and equipment' => ['laboratory apparatus and equipment', 'laboratory apparatus', 'laboratory equipment', 'lab equipment'],
        'library books'                    => ['library books', 'library book', 'books', 'book'],
        'Motor vehicles'                   => ['motor vehicles', 'motor vehicle', 'vehicles', 'vehicle'],
        'P.E Equipment'                    => ['p e equipment', 'pe equipment', 'physical education equipment', 'sports equipment'],
        'Low value Asset'                  => ['low value asset', 'low value assets', 'low value', 'low value items'],
    ];

    /* ═══════════════════════════ read side ═══════════════════════════ */

    public static function batch(int $id): ?object
    {
        return DB::table('asset_import_batches')->where('id', $id)->first();
    }

    /** The newest batch, so the page can pick up where the office left off. */
    public static function latest(?User $user = null): ?object
    {
        $query = DB::table('asset_import_batches')->orderByDesc('id');

        if ($user) {
            $query->where('user_id', $user->id);
        }

        return $query->first();
    }

    /** @return array<int, string> field key => label */
    public static function fieldLabels(): array
    {
        return array_map(static fn ($field) => $field['label'], self::FIELDS);
    }

    /**
     * Match the workbook's headings to our fields.
     *
     * Exact (normalised) matches win for every field first, so a file that has
     * both `ID NO.` and `PROPERTY ID NO` can never map the short heading onto
     * the property column through a partial match.
     *
     * @param  array<int, string>  $headings
     * @return array<string, int|null>
     */
    public static function detectMapping(array $headings): array
    {
        $normalised = [];

        foreach ($headings as $index => $heading) {
            $key = self::headingKey((string) $heading);

            if ($key !== '' && ! isset($normalised[$key])) {
                $normalised[$key] = (int) $index;
            }
        }

        $mapping = array_fill_keys(array_keys(self::FIELDS), null);

        foreach (self::FIELDS as $field => $definition) {
            foreach ($definition['aliases'] as $alias) {
                $index = $normalised[$alias] ?? null;

                if ($index !== null) {
                    $mapping[$field] = $index;
                    break;
                }
            }
        }

        // Anything still unmatched gets one generous attempt: a heading that
        // contains exactly one alias ("Property ID No. (old)"). Only when
        // exactly one column answers, so ambiguity maps nothing.
        foreach (self::FIELDS as $field => $definition) {
            if ($mapping[$field] !== null) {
                continue;
            }

            $candidates = [];

            foreach ($normalised as $key => $index) {
                foreach ($definition['aliases'] as $alias) {
                    if (mb_strlen($alias) >= 6 && str_contains($key, $alias)) {
                        $candidates[$index] = true;
                        break;
                    }
                }
            }

            if (count($candidates) === 1) {
                $mapping[$field] = (int) array_key_first($candidates);
            }
        }

        return $mapping;
    }

    public static function template(): StreamedResponse
    {
        $headings = [
            'ID NO.', 'PO NO', 'PROPERTY ID NO', 'DESCRIPTION', 'DATE ACQUIRED',
            'LOCATION', 'DEPARTMENT', 'TYPE OF ASSET', 'UNIT COST', 'ACCOUNTABLE PERSON', 'ASSET CODE',
        ];

        return Csv::download('nu-trace-asset-import-template.csv', $headings, [[
            '001', 'Donation', '30014399', 'Mobile Pedestal', '19-Jan-22',
            'Health Services', 'COLLEGE OF NURSING', 'Furnitures and Fixtures', '1.00', 'Atienza, Rio', '',
        ]]);
    }

    /* ═══════════════════════════ upload ═══════════════════════════ */

    /**
     * Accept a workbook, read its header and store it for checking.
     *
     * The file is NOT parsed into rows here: only the header is read, so the
     * office can confirm the column mapping before any work is done.
     *
     * @return array{ok: bool, message?: string, batch?: array, sample?: array}
     */
    public static function start(UploadedFile $file, ?User $actor): array
    {
        if (! $file->isValid()) {
            return [
                'ok'      => false,
                'message' => 'The file did not upload completely. Choose it again — nothing was imported.',
            ];
        }

        // There is no size check here on purpose: a workbook of any size is
        // accepted, and the only bound is the disk the staging copy is written
        // to. The page sends a large file in parts so no request has to carry
        // the whole thing.

        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: 'xlsx'));

        // `.xls` is accepted here so a CSV saved under that name is read by its
        // bytes; a real old-format .xls is refused by the reader with a message
        // that says what to do.
        if (! in_array($extension, ['xlsx', 'csv', 'txt', 'tsv', 'xls'], true)) {
            return ['ok' => false, 'message' => 'Only an .xlsx or .csv file can be imported.'];
        }

        self::pruneStale();

        $hash = hash_file('sha256', $file->getRealPath()) ?: null;

        // The same workbook uploaded twice resumes the first batch instead of
        // building a second one. This is a convenience only: duplicate assets
        // are prevented by the property number, not by the fingerprint, so a
        // resaved or renamed file is still caught.
        if ($hash) {
            $existing = DB::table('asset_import_batches')
                ->where('file_hash', $hash)
                ->whereIn('status', ['uploaded', 'checking', 'ready', 'importing'])
                ->orderByDesc('id')
                ->first();

            if ($existing) {
                return [
                    'ok'      => true,
                    'batch'   => self::payload($existing),
                    'sample'  => self::sample($existing),
                    'message' => 'This exact file is already open in the import below — continuing that one instead of starting a second.',
                ];
            }
        }

        $batchId = DB::table('asset_import_batches')->insertGetId([
            'user_id'       => $actor?->id,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'file_hash'     => $hash,
            'size_bytes'    => (int) $file->getSize(),
            'status'        => 'uploaded',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $directory = self::directory($batchId);
        Storage::disk('local')->makeDirectory($directory);

        $stored = $file->storeAs($directory, 'source.' . $extension, 'local');

        if (! $stored) {
            DB::table('asset_import_batches')->where('id', $batchId)->update([
                'status'  => 'failed',
                'message' => 'The file could not be stored on the server.',
            ]);

            return ['ok' => false, 'message' => 'The file could not be stored on the server. Please try again.'];
        }

        $path = Storage::disk('local')->path($stored);

        DB::table('asset_import_batches')->where('id', $batchId)->update([
            'disk_path' => $stored,
            'updated_at' => now(),
        ]);

        $batch = self::batch($batchId);

        try {
            $reader = SpreadsheetReader::open($path, $extension, Storage::disk('local')->path($directory));
            $headings = $reader->headers();
            $reader->close();
        } catch (Throwable $e) {
            DB::table('asset_import_batches')->where('id', $batchId)->update([
                'status'     => 'failed',
                'message'    => $e->getMessage(),
                'updated_at' => now(),
            ]);

            return ['ok' => false, 'message' => $e->getMessage()];
        }

        if ($headings === []) {
            DB::table('asset_import_batches')->where('id', $batchId)->update([
                'status'  => 'failed',
                'message' => 'The file has no header row, so its columns cannot be read.',
            ]);

            return ['ok' => false, 'message' => 'The file has no header row. Download the template to see the expected headings.'];
        }

        $mapping = self::detectMapping($headings);

        DB::table('asset_import_batches')->where('id', $batchId)->update([
            'header'     => json_encode($headings, JSON_UNESCAPED_UNICODE),
            'mapping'    => json_encode($mapping),
            'updated_at' => now(),
        ]);

        self::audit($actor, 'CREATE',
            'Started an inventory import from "' . $file->getClientOriginalName() . '" (' . self::humanSize((int) $file->getSize()) . ').',
            'Inventory import #' . $batchId . ' uploaded — nothing imported yet');

        $batch = self::batch($batchId);

        return [
            'ok'     => true,
            'batch'  => self::payload($batch),
            'sample' => self::sample($batch),
        ];
    }

    /* ═══════════════════════ uploads in parts ═══════════════════════ */

    /**
     * How large one uploaded slice may be.
     *
     * The browser splits a file into slices of this size, so the file a
     * registry may be is never decided by PHP's upload_max_filesize: a 900 MB
     * workbook is simply more slices. The size below is the smaller of
     * ASSET_IMPORT_PART_MB and whatever this server will actually accept in one
     * request, less a margin for the multipart envelope.
     */
    public static function partBytes(): int
    {
        $ceiling = PHP_INT_MAX;

        foreach (['upload_max_filesize', 'post_max_size'] as $setting) {
            $value = self::iniBytes((string) ini_get($setting));

            if ($value > 0) {
                $ceiling = min($ceiling, $value);
            }
        }

        $configured = (int) config('asset_import.part_bytes', 0);
        $wanted = $configured > 0 ? $configured : 8 * 1048576;
        $bytes = min($wanted, $ceiling);

        if ($ceiling !== PHP_INT_MAX) {
            // A body exactly at post_max_size is discarded before Laravel sees
            // it, so leave room for the form fields and the boundary.
            $bytes = min($bytes, max(128 * 1024, $ceiling - 64 * 1024));
        }

        return max(64 * 1024, (int) $bytes);
    }

    /**
     * One slice of a file being uploaded in parts.
     *
     * Slices arrive one after another and are kept under
     * `asset-imports/_parts/<upload_id>/`; nothing is checked until the last
     * missing one lands. Then they are joined in order into a real file and
     * handed to start(), so a workbook uploaded in parts takes exactly the same
     * path as one uploaded in a single request: same size check, same
     * fingerprint, same column detection, same batch.
     *
     * Re-sending a slice simply replaces it, which is what makes a retry after
     * a dropped connection safe.
     *
     * @return array{ok: bool, message?: string, received?: int, total?: int, batch?: array, sample?: array}
     */
    public static function storePart(string $uploadId, int $index, int $total, UploadedFile $part, string $originalName, ?User $actor): array
    {
        if (! preg_match('/^[0-9a-f]{32}$/', $uploadId)) {
            return ['ok' => false, 'message' => 'That upload could not be identified. Reload the page and choose the file again.'];
        }

        if ($total < 1 || $total > 100000 || $index < 0 || $index >= $total) {
            return ['ok' => false, 'message' => 'That upload arrived out of step. Reload the page and try again.'];
        }

        $extension = strtolower((string) (pathinfo($originalName, PATHINFO_EXTENSION) ?: 'xlsx'));

        if (! in_array($extension, ['xlsx', 'csv', 'txt', 'tsv', 'xls'], true)) {
            return ['ok' => false, 'message' => 'Only an .xlsx or .csv file can be imported.'];
        }

        $disk = Storage::disk('local');
        $directory = self::partsDirectory($uploadId);
        $disk->makeDirectory($directory);

        if (! $part->storeAs($directory, $index . '.part', 'local')) {
            return ['ok' => false, 'message' => 'One part of the file could not be stored on the server. Please try again.'];
        }

        // One file per slice, named by its index, so repeated slices overwrite
        // and a retry after a dropped connection costs only that slice.
        $received = count(array_filter(
            $disk->files($directory),
            fn (string $file) => str_ends_with($file, '.part')
        ));

        if ($received < $total) {
            // The answer to the slice that completed the set can be lost in
            // transit. The marker left behind says the file really was joined,
            // so the retried slice opens that batch instead of waiting forever
            // for slices the server has already discarded.
            $joined = $disk->exists($directory . '/joined.txt')
                ? (int) $disk->get($directory . '/joined.txt')
                : 0;

            $batch = $joined > 0 ? self::batch($joined) : null;

            if ($batch) {
                return [
                    'ok'         => true,
                    'batch'      => self::payload($batch),
                    'sample'     => self::sample($batch),
                    'message'    => 'This file was already uploaded — the import below was created from it.',
                    'received'   => $total,
                    'total'      => $total,
                ];
            }

            return ['ok' => true, 'received' => $received, 'total' => $total];
        }

        $assembled = $directory . '/source.' . $extension;

        // Join into a name only this request uses, then rename it into place:
        // a rename is atomic, so a second request that finishes at the same
        // moment can never be handed a half-written workbook.
        $scratch = $directory . '/join-' . $index . '.' . $extension;

        try {
            if (! self::joinParts($disk->path($directory), $total, $disk->path($scratch))) {
                return ['ok' => false, 'message' => 'The parts could not be joined on the server. Please try again.'];
            }

            if (! $disk->move($scratch, $assembled)) {
                return ['ok' => false, 'message' => 'The joined file could not be opened on the server. Please try again.'];
            }

            $file = new UploadedFile($disk->path($assembled), $originalName, $part->getClientMimeType(), null, true);

            $result = self::start($file, $actor);
        } finally {
            // Whatever happened, the slices must not stay on the disk: start()
            // has already moved the joined file into the batch.
            try {
                foreach ($disk->files($directory) as $file) {
                    if (str_ends_with($file, '.part') || str_contains(basename($file), 'join-')) {
                        $disk->delete($file);
                    }
                }
            } catch (Throwable $e) {
                Log::warning('Could not remove import parts for upload ' . $uploadId . ': ' . $e->getMessage());
            }
        }

        if (($result['ok'] ?? false) && isset($result['batch']['id'])) {
            $disk->put($directory . '/joined.txt', (string) $result['batch']['id']);
        }

        return $result + ['received' => $total, 'total' => $total];
    }

    /** Join `<index>.part` files, in order, into one file. */
    private static function joinParts(string $directory, int $total, string $target): bool
    {
        // A large workbook takes a few seconds to join; no request timeout
        // should cut it in half. CLI servers ignore this anyway.
        @set_time_limit(0);

        $out = @fopen($target, 'wb');

        if (! $out) {
            return false;
        }

        try {
            for ($i = 0; $i < $total; $i++) {
                $in = @fopen($directory . '/' . $i . '.part', 'rb');

                if (! $in) {
                    return false;
                }

                $copied = stream_copy_to_stream($in, $out);
                fclose($in);

                if ($copied === false) {
                    return false;
                }
            }
        } finally {
            fclose($out);
        }

        return true;
    }

    private static function partsDirectory(string $uploadId): string
    {
        return 'asset-imports/_parts/' . $uploadId;
    }

    /** PHP's "2M" / "512K" / "1G" ini values, in bytes; 0 when unlimited. */
    private static function iniBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '-1') {
            return 0;
        }

        if (! preg_match('/^([\d.]+)\s*([kmg])?/i', $value, $matches)) {
            return 0;
        }

        $unit = strtolower($matches[2] ?? '');
        $power = ['k' => 1, 'm' => 2, 'g' => 3][$unit] ?? 0;

        return (int) ((float) $matches[1] * (1024 ** $power));
    }

    /** Column mapping confirmed by the office; rows are re-checked from scratch. */
    public static function saveMapping(int $id, array $mapping, ?User $actor): array
    {
        $batch = self::batch($id);

        if (! $batch) {
            return ['ok' => false, 'message' => 'That import no longer exists.'];
        }

        if (in_array($batch->status, ['importing', 'completed'], true)) {
            return ['ok' => false, 'message' => 'The import has already started, so its column mapping can no longer change.'];
        }

        $header = json_decode((string) $batch->header, true) ?: [];
        $clean = array_fill_keys(array_keys(self::FIELDS), null);

        foreach (self::FIELDS as $field => $definition) {
            $value = $mapping[$field] ?? null;

            if ($value === null || $value === '' || $value === 'none') {
                continue;
            }

            $index = (int) $value;

            if ($index >= 0 && $index < 256) {
                $clean[$field] = $index;
            }
        }

        if ($clean['name'] === null) {
            return ['ok' => false, 'message' => 'Choose which column holds the asset description — an asset cannot be imported without a name.'];
        }

        DB::table('asset_import_batches')->where('id', $id)->update([
            'mapping'       => json_encode($clean),
            'status'        => 'uploaded',
            'read_offset'   => 0,
            'rows_checked'  => 0,
            'row_count'     => null,
            'message'       => null,
            'updated_at'    => now(),
        ]);

        DB::table('asset_import_rows')->where('batch_id', $id)->delete();

        self::audit($actor, 'UPDATE',
            'Confirmed the column mapping for inventory import #' . $id . '.',
            'Inventory import mapping saved');

        return ['ok' => true, 'batch' => self::payload(self::batch($id)), 'sample' => self::sample(self::batch($id))];
    }

    /**
     * Check the next window of rows.
     *
     * Reads at most `check_rows` lines (or `check_seconds` of work) from the byte
     * offset the last call stopped at, resolves what each line means, and stores
     * the decision. The workbook is never re-read from the beginning.
     */
    public static function step(int $id, ?User $actor = null): array
    {
        $batch = self::batch($id);

        if (! $batch) {
            return ['ok' => false, 'message' => 'That import no longer exists.'];
        }

        if (in_array($batch->status, ['completed', 'failed'], true)) {
            return ['ok' => true, 'batch' => self::payload($batch), 'done' => true];
        }

        if ($batch->status === 'importing') {
            return ['ok' => true, 'batch' => self::payload($batch), 'done' => true, 'importing' => true];
        }

        $path = self::sourcePath($batch);

        if (! $path) {
            return ['ok' => false, 'message' => 'The uploaded workbook is no longer on the server. Upload it again.'];
        }

        if ($batch->status === 'uploaded') {
            DB::table('asset_import_batches')->where('id', $id)->update([
                'status'     => 'checking',
                'started_at' => $batch->started_at ?: now(),
                'updated_at' => now(),
            ]);
        }

        $mapping = json_decode((string) $batch->mapping, true) ?: [];
        $options = self::optionsFor($batch);
        $context = self::context();

        $limitRows = max(50, (int) config('asset_import.check_rows', 400));
        $limitSeconds = max(2, (int) config('asset_import.check_seconds', 8));
        $deadline = microtime(true) + $limitSeconds;

        try {
            $reader = SpreadsheetReader::open($path, pathinfo((string) $batch->disk_path, PATHINFO_EXTENSION), Storage::disk('local')->path(self::directory($id)));
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        $offset = (int) ($batch->read_offset ?: 0);

        // A fresh batch starts at the first data row, never at the header row:
        // the headings were read to build the mapping and are not an asset.
        if ($offset <= 0) {
            $offset = $reader->dataOffset();
        }

        $pending = [];
        $processed = 0;
        $exhausted = true;
        $newOffset = $offset;

        // Codes already present in this batch, so a code cannot be used twice
        // across two windows.
        $batchCodes = DB::table('asset_import_rows')
            ->where('batch_id', $id)
            ->whereNotNull('asset_code')
            ->get(['line', 'asset_code'])
            ->mapWithKeys(fn ($row) => [mb_strtoupper((string) $row->asset_code) => (int) $row->line])
            ->all();

        // Existing assets whose identifiers could collide with this window.
        $existingCache = self::existingCache();

        foreach ($reader->rowsFrom($offset) as $row) {
            $payload = self::payloadFromCells($row['cells'], $mapping);

            $pending[] = [
                'line'    => $row['line'],
                'payload' => $payload,
                'code'    => $payload['asset_code'] !== '' ? AssetCode::normalize($payload['asset_code']) : null,
            ];

            $newOffset = (int) $row['offset'];
            $processed++;

            if ($processed >= $limitRows || microtime(true) >= $deadline) {
                $exhausted = false;
                break;
            }
        }

        $done = $exhausted;

        // One line at a time in the buffer, but one INSERT per 150 rows.
        $insert = [];

        foreach ($pending as $item) {
            $code = $item['code'];
            $plan = self::plan($item['payload'], $code, $context, $options, $existingCache, $batchCodes, $item['line'], $batch->original_name);

            if ($code !== null && $code !== '') {
                $batchCodes[$code] ??= $item['line'];
            }

            $insert[] = [
                'batch_id'            => $id,
                'line'                => $item['line'],
                'status'              => $plan['status'],
                'action'              => $plan['action'],
                'property_id_no'      => $plan['property_id_no'],
                'inventory_id_no'     => $plan['inventory_id_no'],
                'asset_code'          => $code,
                'payload'             => json_encode($item['payload'], JSON_UNESCAPED_UNICODE),
                'resolved'            => json_encode($plan['resolved'], JSON_UNESCAPED_UNICODE),
                'issues'              => json_encode($plan['issues'], JSON_UNESCAPED_UNICODE),
                'department_id'       => $plan['department_id'],
                'accountable_name'    => $plan['accountable_name'],
                'accountable_user_id' => $plan['accountable_user_id'],
                'created_at'          => now(),
                'updated_at'          => now(),
            ];
        }

        foreach (array_chunk($insert, 150) as $chunk) {
            DB::table('asset_import_rows')->insert($chunk);
        }

        // A property number used twice inside the file is a conflict on both
        // lines, including a later window's line colliding with an earlier one.
        self::flagRepeatedProperties($id);

        $rowsChecked = (int) DB::table('asset_import_rows')->where('batch_id', $id)->count();

        DB::table('asset_import_batches')->where('id', $id)->update([
            'read_offset'  => $newOffset,
            'rows_checked' => $rowsChecked,
            'row_count'    => $done ? $rowsChecked : null,
            'status'       => $done ? 'ready' : 'checking',
            'message'      => null,
            'updated_at'   => now(),
        ]);

        $reader->close();

        if ($done) {
            // The workbook is no longer needed: every line is staged.
            self::removeFiles(self::batch($id));

            DB::table('asset_import_batches')->where('id', $id)->update([
                'finished_at' => now(),
                'message'     => 'Every line has been checked. Review the preview, then import.',
            ]);

            self::audit($actor, 'UPDATE',
                'Checked ' . $rowsChecked . ' line(s) of inventory import #' . $id . ' — nothing imported yet.',
                'Inventory import check completed');
        }

        $batch = self::batch($id);

        return [
            'ok'      => true,
            'batch'   => self::payload($batch),
            'done'    => $done,
            'summary' => self::summary($id),
        ];
    }

    /* ═══════════════════════════ corrections ═══════════════════════════ */

    /**
     * Every distinct value that still needs an office decision.
     *
     * Departments and asset types are short lists, so they are resolved once for
     * the whole file. Accountable-person names are listed only when the lookup
     * could not settle them.
     */
    public static function decisions(int $id): array
    {
        $batch = self::batch($id);

        if (! $batch) {
            return ['departments' => [], 'categories' => [], 'people' => [], 'options' => []];
        }

        $options = self::optionsFor($batch);
        $departments = [];
        $categories = [];
        $people = [];

        foreach (DB::table('asset_import_rows')->where('batch_id', $id)->orderBy('line')->get() as $row) {
            $resolved = json_decode((string) $row->resolved, true) ?: [];
            $payload = json_decode((string) $row->payload, true) ?: [];

            $status = $resolved['department_status'] ?? 'matched';

            if (in_array($status, ['possible', 'unmatched'], true)) {
                $key = (string) ($payload['department'] ?? '');
                $departments[$key] ??= [
                    'value'     => $key,
                    'count'     => 0,
                    'status'    => $status,
                    'suggested' => $resolved['department_suggested'] ?? null,
                    'chosen'    => ($options['departments'][self::departmentKey($key)] ?? null),
                ];
                $departments[$key]['count']++;
            }

            if (($resolved['category_status'] ?? 'matched') !== 'matched') {
                $key = (string) ($payload['category'] ?? '');
                $categories[$key] ??= [
                    'value'  => $key,
                    'count'  => 0,
                    'status' => $resolved['category_status'] ?? 'unmatched',
                    'chosen' => ($options['categories'][self::categoryKey($key)] ?? null),
                ];
                $categories[$key]['count']++;
            }

            if (($resolved['accountable_status'] ?? '') === self::PERSON_AMBIGUOUS) {
                $key = (string) ($payload['accountable'] ?? '');
                $chosen = $options['people'][self::personKey($key)] ?? null;
                $people[$key] ??= [
                    'value'      => $key,
                    'count'      => 0,
                    'candidates' => $resolved['accountable_candidates'] ?? [],
                    'chosen'     => $chosen === '' || $chosen === null ? null : (int) $chosen,
                    'decided'    => array_key_exists(self::personKey($key), $options['people']),
                ];
                $people[$key]['count']++;
            }
        }

        ksort($departments);
        ksort($categories);
        ksort($people);

        return [
            'departments' => array_values($departments),
            'categories'  => array_values($categories),
            'people'      => array_values($people),
            'options'     => [
                'departments'                => (object) ($options['departments'] ?? []),
                'categories'                 => (object) ($options['categories'] ?? []),
                'people'                     => (object) ($options['people'] ?? []),
                'allow_missing_department'   => (bool) ($options['allow_missing_department'] ?? false),
                'allow_blank_property'       => (bool) ($options['allow_blank_property'] ?? false),
            ],
        ];
    }

    /**
     * Save the office's decisions and re-check every staged row.
     *
     * The file is not read again: each row keeps the values it was read with, so
     * a correction is applied to what the office actually reviewed.
     */
    public static function saveDecisions(int $id, array $input, ?User $actor): array
    {
        $batch = self::batch($id);

        if (! $batch) {
            return ['ok' => false, 'message' => 'That import no longer exists.'];
        }

        if (in_array($batch->status, ['importing', 'completed'], true)) {
            return ['ok' => false, 'message' => 'The import has already started, so its mapping can no longer change.'];
        }

        $existing = self::optionsFor($batch);

        $departments = self::cleanMap($input['departments'] ?? [], 'department');
        $categories = self::cleanMap($input['categories'] ?? [], 'category');
        $people = self::cleanMap($input['people'] ?? [], 'person');

        // Only departments that exist, only categories the schema accepts.
        $validDepartments = DB::table('departments')->pluck('id')->map(fn ($v) => (int) $v)->all();
        $departments = array_filter($departments, fn ($value) => in_array((int) $value, $validDepartments, true));

        $categories = array_filter($categories, fn ($value) => in_array($value, self::CATEGORIES, true));

        $validUsers = DB::table('users')
            ->join('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
            ->where('users.status', 'Active')
            ->pluck('users.id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $people = array_filter($people, fn ($value) => in_array((int) $value, $validUsers, true));

        $options = [
            'departments'              => (object) $departments,
            'categories'               => (object) $categories,
            'people'                   => (object) $people,
            'allow_missing_department' => (bool) ($input['allow_missing_department'] ?? false),
            'allow_blank_property'     => (bool) ($input['allow_blank_property'] ?? false),
        ];

        // A name the office deliberately left unassigned is remembered as an
        // empty string, which is different from "not decided yet".
        foreach ((array) ($input['people_unassigned'] ?? []) as $name) {
            $key = self::personKey((string) $name);

            if ($key !== '') {
                $options['people']->{$key} = '';
            }
        }

        DB::table('asset_import_batches')->where('id', $id)->update([
            'options'    => json_encode($options),
            'status'     => 'checking',
            'updated_at' => now(),
        ]);

        $result = self::replan($id);

        self::audit($actor, 'UPDATE',
            'Applied department / asset-type / accountable-person decisions to inventory import #' . $id . '.',
            'Inventory import mapping decisions saved');

        return ['ok' => true, 'message' => $result['message'] ?? 'Decisions saved and every line re-checked.', 'summary' => self::summary($id)];
    }

    /**
     * Set custom Asset Codes for specific lines.
     *
     * @param  array<int|string, string>  $codes  line => code
     */
    public static function saveCodes(int $id, array $codes, ?User $actor): array
    {
        $batch = self::batch($id);

        if (! $batch) {
            return ['ok' => false, 'message' => 'That import no longer exists.'];
        }

        if (in_array($batch->status, ['importing', 'completed'], true)) {
            return ['ok' => false, 'message' => 'The import has already started, so codes can no longer change.'];
        }

        $lines = DB::table('asset_import_rows')->where('batch_id', $id)->pluck('line')->map(fn ($v) => (int) $v)->all();
        $saved = 0;

        foreach ($codes as $line => $code) {
            $line = (int) $line;

            if (! in_array($line, $lines, true)) {
                continue;
            }

            $code = trim((string) $code);
            $normalised = $code === '' ? null : AssetCode::normalize($code);

            DB::table('asset_import_rows')
                ->where('batch_id', $id)
                ->where('line', $line)
                ->update(['asset_code' => $normalised, 'updated_at' => now()]);

            $saved++;
        }

        self::replan($id);

        self::audit($actor, 'UPDATE',
            'Assigned custom asset codes to ' . $saved . ' line(s) of inventory import #' . $id . '.',
            'Inventory import asset codes saved');

        return [
            'ok'      => true,
            'message' => $saved . ' line(s) updated and re-checked.',
            'summary' => self::summary($id),
        ];
    }

    /**
     * Choose the accountable person for one line of the file.
     *
     * A name on the spreadsheet often does not match the employee masterlist
     * (a nickname, a resigned employee, a name with no account yet). Rather than
     * leaving the office to fix the paper and re-upload, the review table lets
     * them pick the account for that line here:
     *
     *   $userId = null  back to whatever the file's name resolves to
     *   $userId = 0     deliberately unassigned, keeping the name that was read
     *   $userId > 0     the user account they chose
     *
     * The choice is stored on the row, so re-checking the batch (or any later
     * decision) keeps it, and the rest of the batch is re-planned around it.
     */
    public static function assignPerson(int $id, int $line, ?int $userId, ?User $actor): array
    {
        $batch = self::batch($id);

        if (! $batch) {
            return ['ok' => false, 'message' => 'That import no longer exists.'];
        }

        if (in_array($batch->status, ['importing', 'completed'], true)) {
            return ['ok' => false, 'message' => 'The import has already started, so its rows can no longer change.'];
        }

        $row = DB::table('asset_import_rows')->where('batch_id', $id)->where('line', $line)->first();

        if (! $row) {
            return ['ok' => false, 'message' => 'That line is not part of this import.'];
        }

        if (in_array($row->status, [self::IMPORTED, self::FAILED], true)) {
            return ['ok' => false, 'message' => 'That line has already been written, so its accountable person can only be changed on the asset page.'];
        }

        if ($userId !== null && $userId > 0) {
            $valid = DB::table('users')
                ->join('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
                ->where('users.id', $userId)
                ->where('users.status', 'Active')
                ->exists();

            if (! $valid) {
                return ['ok' => false, 'message' => 'That account is not active, so it cannot hold an asset. Choose another employee.'];
            }
        } else {
            // null clears the override; 0 is the explicit "leave unassigned".
            $userId = $userId === 0 ? 0 : null;
        }

        DB::table('asset_import_rows')->where('id', $row->id)->update([
            'accountable_override' => $userId,
            'updated_at'           => now(),
        ]);

        self::replan($id);

        $fresh = DB::table('asset_import_rows')->where('id', $row->id)->first();

        self::audit($actor, 'UPDATE',
            'Chose the accountable person for line ' . $line . ' of inventory import #' . $id . '.',
            'Inventory import accountable person changed');

        return [
            'ok'        => true,
            'message'   => 'Accountable person updated for line ' . $line . '.',
            'row'       => $fresh ? self::rowPayload($fresh) : null,
            'summary'   => self::summary($id),
            'decisions' => self::decisions($id),
        ];
    }

    /**
     * Fill blank codes from a pattern such as `{property_id}` or `AST-{seq}`.
     *
     * Nothing is imported and no format is imposed: the office types the pattern
     * it agreed with the client, sees the proposal in the table, and can still
     * edit any line by hand.
     */
    public static function proposeCodes(int $id, string $pattern, ?User $actor): array
    {
        $batch = self::batch($id);

        if (! $batch) {
            return ['ok' => false, 'message' => 'That import no longer exists.'];
        }

        $pattern = trim($pattern);

        if ($pattern === '') {
            return ['ok' => false, 'message' => 'Enter a pattern that includes at least {property_id}, {inventory_id}, {seq} or {index}.'];
        }

        if (! preg_match('/\{(property_id|inventory_id|seq|index|year)\}/', $pattern)) {
            return ['ok' => false, 'message' => 'That pattern would give every asset the same code. Include {property_id}, {inventory_id}, {seq}, {index} or {year}.'];
        }

        $rows = DB::table('asset_import_rows')
            ->where('batch_id', $id)
            ->whereIn('status', [self::READY, self::REVIEW, self::BLOCKED])
            ->where(fn ($query) => $query->whereNull('asset_code')->orWhere('asset_code', ''))
            ->orderBy('line')
            ->get(['line', 'payload', 'resolved']);

        $used = DB::table('assets')->pluck('Asset_code')->map(fn ($v) => mb_strtoupper((string) $v))->flip()->all();
        $batchUsed = DB::table('asset_import_rows')->where('batch_id', $id)->whereNotNull('asset_code')->pluck('asset_code')->map(fn ($v) => mb_strtoupper((string) $v))->flip()->all();

        $filled = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $payload = json_decode((string) $row->payload, true) ?: [];
            $resolved = json_decode((string) $row->resolved, true) ?: [];

            $candidate = strtr($pattern, [
                '{property_id}'  => (string) ($payload['property_id_no'] ?? ''),
                '{inventory_id}' => (string) ($payload['inventory_id_no'] ?? ''),
                '{seq}'          => (string) $row->line,
                '{index}'        => (string) ($filled + 1),
                '{year}'         => substr((string) ($resolved['date'] ?? ''), 0, 4) ?: now()->format('Y'),
            ]);

            $candidate = AssetCode::normalize($candidate);

            if ($candidate === '' || ! AssetCode::isValid($candidate) || isset($used[$candidate]) || isset($batchUsed[$candidate])) {
                $skipped++;

                continue;
            }

            DB::table('asset_import_rows')->where('batch_id', $id)->where('line', $row->line)->update([
                'asset_code' => $candidate,
                'updated_at' => now(),
            ]);

            $batchUsed[$candidate] = true;
            $filled++;
        }

        self::replan($id);

        self::audit($actor, 'UPDATE',
            'Proposed ' . $filled . ' asset code(s) for inventory import #' . $id . ' using the pattern "' . mb_substr($pattern, 0, 60) . '".',
            'Inventory import asset codes proposed');

        return [
            'ok'      => true,
            'message' => $filled . ' code(s) proposed' . ($skipped ? ', ' . $skipped . ' left blank because the pattern gave an unusable or already-used code' : '') . '. Review them before importing.',
            'summary' => self::summary($id),
        ];
    }

    /* ═══════════════════════════ review ═══════════════════════════ */

    /** @return array{total:int, page:int, pages:int, rows:array<int, array>} */
    public static function rows(int $id, string $status = 'all', int $page = 1): array
    {
        $perPage = max(10, (int) config('asset_import.preview_rows', 50));

        $query = DB::table('asset_import_rows')->where('batch_id', $id);

        if ($status !== '' && $status !== 'all') {
            if ($status === 'attention') {
                $query->whereIn('status', [self::BLOCKED, self::REVIEW]);
            } else {
                $query->where('status', $status);
            }
        }

        $total = (clone $query)->count();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);

        $rows = $query->orderBy('line')->forPage($page, $perPage)->get();

        return [
            'total' => $total,
            'page'  => $page,
            'pages' => $pages,
            'rows'  => $rows->map(fn ($row) => self::rowPayload($row))->all(),
        ];
    }

    public static function summary(int $id): array
    {
        $counts = DB::table('asset_import_rows')
            ->where('batch_id', $id)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($value) => (int) $value)
            ->all();

        $imported = DB::table('asset_import_rows')->where('batch_id', $id)->where('status', self::IMPORTED);

        return [
            'total'      => array_sum($counts),
            'ready'      => $counts[self::READY] ?? 0,
            'review'     => $counts[self::REVIEW] ?? 0,
            'blocked'    => $counts[self::BLOCKED] ?? 0,
            'exists'     => $counts[self::EXISTS] ?? 0,
            'imported'   => $counts[self::IMPORTED] ?? 0,
            'failed'     => $counts[self::FAILED] ?? 0,
            'importable' => ($counts[self::READY] ?? 0) + ($counts[self::REVIEW] ?? 0),
            'unassigned' => (clone $imported)->whereNull('accountable_user_id')->count(),
            'qr_pending' => (clone $imported)->where('qr_status', 'failed')->count(),
        ];
    }

    /**
     * Codes this import created, for the sticker sheet after it finishes.
     *
     * Only assets that were really written are listed, and only a screenful: a
     * label sheet is opened per department, not for a thousand stickers at once.
     *
     * @return array<int, string>
     */
    public static function importedCodes(int $id, int $limit = 0): array
    {
        $limit = $limit > 0 ? $limit : (int) config('asset_import.label_batch_limit', 200);

        return DB::table('asset_import_rows')
            ->join('assets', 'assets.id', '=', 'asset_import_rows.asset_id')
            ->where('asset_import_rows.batch_id', $id)
            ->where('asset_import_rows.status', self::IMPORTED)
            ->orderBy('asset_import_rows.line')
            ->limit($limit)
            ->pluck('assets.Asset_code')
            ->all();
    }

    public static function payload(?object $batch): ?array
    {
        if (! $batch) {
            return null;
        }

        return [
            'id'            => (int) $batch->id,
            'status'        => $batch->status,
            'original_name' => $batch->original_name,
            'size_bytes'    => (int) $batch->size_bytes,
            'rows_checked'  => (int) $batch->rows_checked,
            'row_count'     => $batch->row_count !== null ? (int) $batch->row_count : null,
            'header'        => json_decode((string) $batch->header, true) ?: [],
            'mapping'       => json_decode((string) $batch->mapping, true) ?: [],
            'message'       => $batch->message,
            'started_at'    => $batch->started_at ? Carbon::parse($batch->started_at)->format('M j, Y g:i A') : null,
            'finished_at'   => $batch->finished_at ? Carbon::parse($batch->finished_at)->format('M j, Y g:i A') : null,
            'can_edit'      => ! in_array($batch->status, ['importing', 'completed'], true),
            'summary'       => self::summary((int) $batch->id),
        ];
    }

    /** The first few data rows, shown while the mapping is being confirmed. */
    private static function sample(object $batch): array
    {
        $path = self::sourcePath($batch);

        if (! $path) {
            return [];
        }

        $mapping = json_decode((string) $batch->mapping, true) ?: [];
        $samples = [];

        try {
            $reader = SpreadsheetReader::open(
                $path,
                pathinfo((string) $batch->disk_path, PATHINFO_EXTENSION),
                Storage::disk('local')->path(self::directory((int) $batch->id))
            );

            foreach ($reader->rowsFrom($reader->dataOffset()) as $row) {
                $samples[] = ['line' => $row['line'], 'values' => self::payloadFromCells($row['cells'], $mapping)];

                if (count($samples) >= 5) {
                    break;
                }
            }

            $reader->close();
        } catch (Throwable $e) {
            return [];
        }

        return $samples;
    }

    /* ═══════════════════════════ import ═══════════════════════════ */

    /**
     * Write the next window of approved rows into `assets`.
     *
     * Each row is its own transaction, so a row that fails takes nothing else
     * with it, and the identity guards run again here: a property number that
     * appeared between checking and importing is skipped rather than duplicated.
     */
    public static function commit(int $id, ?User $actor): array
    {
        $batch = self::batch($id);

        if (! $batch) {
            return ['ok' => false, 'message' => 'That import no longer exists.'];
        }

        if ($batch->status === 'completed') {
            return ['ok' => true, 'done' => true, 'batch' => self::payload($batch), 'summary' => self::summary($id), 'message' => 'This import has already finished.'];
        }

        if (in_array($batch->status, ['uploaded', 'checking'], true)) {
            return ['ok' => false, 'message' => 'The file is still being checked. Wait for the check to finish, then import.'];
        }

        if ($batch->status === 'failed') {
            return ['ok' => false, 'message' => $batch->message ?: 'This import cannot continue. Upload the file again.'];
        }

        DB::table('asset_import_batches')->where('id', $id)->update([
            'status'     => 'importing',
            'updated_at' => now(),
        ]);

        $limitRows = max(20, (int) config('asset_import.import_rows', 100));
        $limitSeconds = max(3, (int) config('asset_import.import_seconds', 12));
        $deadline = microtime(true) + $limitSeconds;

        $rows = DB::table('asset_import_rows')
            ->where('batch_id', $id)
            ->whereIn('status', [self::READY, self::REVIEW])
            ->whereNull('asset_id')
            ->orderBy('line')
            ->limit($limitRows)
            ->get();

        $imported = 0;

        foreach ($rows as $row) {
            if (microtime(true) >= $deadline) {
                break;
            }

            if (self::importRow($row, $batch, $actor)) {
                $imported++;
            }
        }

        $remaining = DB::table('asset_import_rows')
            ->where('batch_id', $id)
            ->whereIn('status', [self::READY, self::REVIEW])
            ->whereNull('asset_id')
            ->count();

        $done = $remaining === 0;

        if ($done) {
            $summary = self::summary($id);

            DB::table('asset_import_batches')->where('id', $id)->update([
                'status'      => 'completed',
                'finished_at' => now(),
                'message'     => $summary['imported'] . ' asset(s) imported, ' . $summary['exists'] . ' skipped as already registered, '
                    . $summary['blocked'] . ' blocked, ' . $summary['failed'] . ' failed.',
                'updated_at'  => now(),
            ]);

            self::removeFiles(self::batch($id));

            self::audit($actor, 'CREATE',
                'Finished inventory import #' . $id . ' from "' . $batch->original_name . '": '
                    . $summary['imported'] . ' imported, ' . $summary['exists'] . ' already in NU Trace, '
                    . $summary['blocked'] . ' blocked, ' . $summary['failed'] . ' failed.',
                'Inventory import completed');
        }

        $batch = self::batch($id);

        return [
            'ok'       => true,
            'done'     => $done,
            'imported' => $imported,
            'batch'    => self::payload($batch),
            'summary'  => self::summary($id),
        ];
    }

    /** Re-open rows whose database write failed, so a retry is possible. */
    public static function retryFailed(int $id, ?User $actor): array
    {
        $batch = self::batch($id);

        if (! $batch) {
            return ['ok' => false, 'message' => 'That import no longer exists.'];
        }

        $reopened = DB::table('asset_import_rows')
            ->where('batch_id', $id)
            ->where('status', self::FAILED)
            ->update([
                'status'     => self::READY,
                'message'    => null,
                'updated_at' => now(),
            ]);

        if ($reopened > 0) {
            DB::table('asset_import_batches')->where('id', $id)->update([
                'status'     => 'ready',
                'finished_at' => null,
                'updated_at' => now(),
            ]);

            self::audit($actor, 'UPDATE',
                'Re-opened ' . $reopened . ' failed line(s) of inventory import #' . $id . ' for a retry.',
                'Inventory import retry');
        }

        return ['ok' => true, 'message' => $reopened . ' line(s) re-opened. Import again — an asset that was already created is skipped, never duplicated.', 'summary' => self::summary($id)];
    }

    /**
     * Mint stickers for imported assets whose QR generation failed.
     *
     * The asset is already saved, so this only produces the image: it cannot
     * create a second asset, and it does not change the asset's code.
     */
    public static function retryQr(int $id, ?User $actor): array
    {
        $rows = DB::table('asset_import_rows')
            ->where('batch_id', $id)
            ->where('status', self::IMPORTED)
            ->whereNotNull('asset_id')
            ->where(fn ($query) => $query->where('qr_status', 'failed')->orWhereNull('qr_status'))
            ->limit(100)
            ->get(['id', 'asset_id']);

        $done = 0;
        $failed = 0;

        foreach ($rows as $row) {
            try {
                $path = AssetQr::saveSticker((int) $row->asset_id, 300);
            } catch (Throwable $e) {
                report($e);
                $path = null;
            }

            DB::table('asset_import_rows')->where('id', $row->id)->update([
                'qr_status'  => $path ? 'generated' : 'failed',
                'message'    => $path ? null : 'The asset is saved but its sticker image could not be produced yet. Retry when storage is reachable.',
                'updated_at' => now(),
            ]);

            $path ? $done++ : $failed++;
        }

        if ($done > 0) {
            self::audit($actor, 'UPDATE',
                'Generated ' . $done . ' missing QR sticker image(s) for inventory import #' . $id . '.',
                'Inventory import QR stickers generated');
        }

        return [
            'ok'      => true,
            'message' => $done . ' sticker image(s) generated' . ($failed ? ', ' . $failed . ' still failing' : '') . '.',
            'summary' => self::summary($id),
        ];
    }

    /** Remove a batch the office abandoned, and anything it left on disk. */
    public static function cancel(int $id, ?User $actor): array
    {
        $batch = self::batch($id);

        if (! $batch) {
            return ['ok' => false, 'message' => 'That import no longer exists.'];
        }

        if ($batch->status === 'importing') {
            return ['ok' => false, 'message' => 'The import is running. Wait for it to finish before discarding it.'];
        }

        // Assets already written are kept: an import does not delete records it
        // has already created safely.
        self::removeFiles($batch);
        DB::table('asset_import_rows')->where('batch_id', $id)->delete();
        DB::table('asset_import_batches')->where('id', $id)->delete();

        self::audit($actor, 'UPDATE',
            'Discarded inventory import #' . $id . ' ("' . $batch->original_name . '"). Assets already imported were kept.',
            'Inventory import discarded');

        return ['ok' => true, 'message' => 'Import discarded. Any assets already imported were kept.'];
    }

    /* ═══════════════════════════ planning ═══════════════════════════ */

    /**
     * Decide what to do with one line.
     *
     * Pure: everything needed (departments, employees, existing assets, batch
     * codes) is passed in, so the same function answers both "what will happen
     * if I import this?" and "what happens now that I changed the department?".
     */
    private static function plan(
        array $payload,
        ?string $code,
        array $context,
        array $options,
        array &$existingCache,
        array $batchCodes,
        int $line,
        string $fileName,
        ?int $personOverride = null
    ): array {
        $errors = [];
        $warnings = [];

        $propertyId = trim((string) ($payload['property_id_no'] ?? ''));
        $inventoryId = trim((string) ($payload['inventory_id_no'] ?? ''));
        $name = trim((string) ($payload['name'] ?? ''));
        $location = trim((string) ($payload['location'] ?? ''));
        $dateRaw = trim((string) ($payload['accusion_date'] ?? ''));
        $costRaw = trim((string) ($payload['unit_cost'] ?? ''));
        $personRaw = trim((string) ($payload['accountable'] ?? ''));

        $date = self::parseDate($dateRaw);

        if ($dateRaw !== '' && $date === null) {
            $errors[] = 'Date Acquired "' . mb_substr($dateRaw, 0, 40) . '" is not a date this system can read.';
        }

        if ($name === '') {
            $errors[] = 'The description is empty, so this line has no asset name.';
        }

        $propertyKey = self::identifierKey($propertyId);
        $existing = $propertyKey !== '' ? ($existingCache['property'][$propertyKey] ?? null) : null;

        // Case A: the property number is already in NU Trace. The line is
        // skipped whatever else it says - it identifies the same asset.
        if ($existing) {
            return [
                'status'              => self::EXISTS,
                'action'              => 'skip',
                'issues'              => [['level' => 'info', 'text' => 'Already in NU Trace as ' . $existing['code'] . ($existing['name'] ? ' — ' . $existing['name'] : '') . '. Kept as it is; nothing is overwritten.']],
                'resolved'            => [
                    'date'               => $date,
                    'department_status'  => 'n/a',
                    'category'           => null,
                    'accountable_status' => 'n/a',
                    'existing_asset_id'  => $existing['id'],
                ],
                'department_id'       => null,
                'accountable_user_id' => null,
                'accountable_name'    => $personRaw !== '' ? mb_substr($personRaw, 0, 150) : null,
                'property_id_no'      => $propertyId !== '' ? mb_substr($propertyId, 0, 100) : null,
                'inventory_id_no'     => $inventoryId !== '' ? mb_substr($inventoryId, 0, 100) : null,
            ];
        }

        // Case D: no property number. Identity is never guessed from the
        // description, cost or ID NO., so the whole file waits for a decision.
        if ($propertyId === '') {
            if ($options['allow_blank_property'] ?? false) {
                $warnings[] = 'No Property ID No. on this line. It is imported on the office\'s instruction, with no duplicate key of its own.';
            } else {
                $errors[] = 'No Property ID No. This line cannot be matched to an existing asset without one.';
            }
        } elseif (mb_strlen($propertyId) > 100) {
            $errors[] = 'The Property ID No. is longer than 100 characters.';
        }

        // Case F: the ID NO. belongs to a known asset, but the property number
        // does not. Not merged, not rejected — shown so a human decides.
        $inventoryKey = self::identifierKey($inventoryId);

        if ($inventoryKey !== '' && isset($existingCache['inventory'][$inventoryKey])) {
            $match = $existingCache['inventory'][$inventoryKey];
            $warnings[] = 'ID NO. ' . $inventoryId . ' is already used by ' . $match['code']
                . ' (Property ID No. ' . ($match['property_id_no'] ?: '—') . '). The property numbers differ, so this is imported as a separate asset and the ID NO. is kept for review.';
        }

        $department = self::resolveDepartment((string) ($payload['department'] ?? ''), $context, $options);

        if ($department['status'] === 'unmatched' || $department['status'] === 'possible') {
            if ($options['allow_missing_department'] ?? false) {
                $warnings[] = 'Department "' . mb_substr((string) ($payload['department'] ?? ''), 0, 60) . '" is not matched. Imported without a department, as instructed.';
            } else {
                $errors[] = 'Department "' . mb_substr((string) ($payload['department'] ?? ''), 0, 60) . '" is not in the department list. Choose the right department (or decide to import without one) before importing.';
            }
        }

        $category = self::resolveCategory((string) ($payload['category'] ?? ''), $options);

        if (! in_array($category['status'], ['matched', 'chosen'], true)) {
            $errors[] = 'Type of asset "' . mb_substr((string) ($payload['category'] ?? ''), 0, 60) . '" is not one of the categories NU Trace uses. Choose the matching category before importing.';
        }

        // An accountable person the office picked during review wins over the
        // name in the file: they have seen the line, the name has not.
        $person = $personOverride === null
            ? self::resolvePerson($personRaw, $context, $options)
            : self::overridePerson($personOverride, $personRaw, $context);

        if ($person['status'] === self::PERSON_AMBIGUOUS) {
            $errors[] = 'More than one employee matches "' . mb_substr($personRaw, 0, 60) . '". Choose the right person, or leave the asset unassigned, before importing.';
        } elseif ($person['status'] === self::PERSON_NO_ACCOUNT) {
            $warnings[] = $person['display'] . ' is on the employee masterlist but has no active NU Trace account yet. The asset is imported unassigned and the name is kept until they register.';
        } elseif ($person['status'] === self::PERSON_INACTIVE_ACCOUNT) {
            $warnings[] = $person['display'] . '\'s NU Trace account is not active. The asset is imported unassigned.';
        } elseif ($person['status'] === self::PERSON_INACTIVE_EMPLOYEE) {
            $warnings[] = $person['display'] . '\'s employee record is no longer Active. The asset is imported unassigned.';
        } elseif ($person['status'] === self::PERSON_NO_EMPLOYEE && $personRaw !== '') {
            $warnings[] = 'Nobody on the employee masterlist matches "' . mb_substr($personRaw, 0, 60) . '". The asset is imported unassigned and the name is kept.';
        }

        $code = $code !== null ? AssetCode::normalize($code) : '';

        if ($code === '') {
            $errors[] = 'No asset code yet. Enter the office\'s code for this line, or propose codes from a pattern.';
        } elseif (! AssetCode::isValid($code)) {
            $errors[] = '“' . mb_substr($code, 0, 50) . '” is not a usable asset code. Use letters, numbers, dot, dash, slash or underscore (2–' . AssetCode::MAX_LENGTH . ' characters).';
        } else {
            if (isset($batchCodes[$code]) && (int) $batchCodes[$code] !== $line) {
                $errors[] = '“' . $code . '” is also used on line ' . $batchCodes[$code] . ' of this file. Every asset needs its own code.';
            }

            $taken = $existingCache['codes'][$code] ?? null;

            if ($taken) {
                $errors[] = '“' . $taken['code'] . '” is already used by ' . ($taken['name'] ?: 'another asset') . ' in NU Trace. Choose a different code.';
            }
        }

        if ($location === '') {
            $warnings[] = 'No location recorded.';
        }

        if ($dateRaw === '') {
            $warnings[] = 'No acquisition date recorded.';
        }

        $level = ! empty($errors) ? self::BLOCKED : (! empty($warnings) ? self::REVIEW : self::READY);

        $issues = [];

        foreach ($errors as $text) {
            $issues[] = ['level' => 'error', 'text' => $text];
        }

        foreach ($warnings as $text) {
            $issues[] = ['level' => 'warning', 'text' => $text];
        }

        return [
            'status'              => $level,
            'action'              => $level === self::BLOCKED ? null : 'import',
            'issues'              => $issues,
            'resolved'            => [
                'date'                   => $date,
                'unit_cost'              => self::parseCost($costRaw),
                'location'               => $location,
                'department_status'      => $department['status'],
                'department_name'        => $department['name'],
                'department_suggested'   => $department['suggested'],
                'category_status'        => $category['status'],
                'category'               => $category['value'],
                'accountable_status'     => $person['status'],
                'accountable_display'    => $person['display'],
                'accountable_employee_id' => $person['employee_id'],
                'accountable_candidates' => $person['candidates'],
                'existing_asset_id'      => null,
                'source'                 => $fileName,
            ],
            'department_id'       => $department['id'],
            'accountable_user_id' => $person['user_id'],
            'accountable_name'    => mb_substr((string) ($person['name'] ?? $personRaw), 0, 150) ?: null,
            'property_id_no'      => $propertyId !== '' ? mb_substr($propertyId, 0, 100) : null,
            'inventory_id_no'     => $inventoryId !== '' ? mb_substr($inventoryId, 0, 100) : null,
        ];
    }

    /**
     * Re-run the plan for staged rows, after a code or a decision changed.
     *
     * All lookups happen once and every row is planned in memory; the updates go
     * back in chunks, so re-checking a long file does not cost one query per row.
     */
    private static function replan(int $id): array
    {
        $batch = self::batch($id);

        if (! $batch) {
            return ['ok' => false, 'message' => 'That import no longer exists.'];
        }

        $options = self::optionsFor($batch);
        $context = self::context();
        $existing = [
            'property'  => self::existingProperties(null),
            'inventory' => self::existingInventory(null),
            'codes'     => AssetCode::taken(DB::table('assets')->pluck('Asset_code')->all()),
        ];

        $rows = DB::table('asset_import_rows')->where('batch_id', $id)->orderBy('line')->get();

        $codeMap = [];

        foreach ($rows as $row) {
            if (! empty($row->asset_code)) {
                $codeMap[mb_strtoupper((string) $row->asset_code)] ??= (int) $row->line;
            }
        }

        $updates = [];

        foreach ($rows as $row) {
            if (in_array($row->status, [self::IMPORTED, self::FAILED], true)) {
                continue;
            }

            $payload = json_decode((string) $row->payload, true) ?: [];
            $plan = self::plan(
                $payload,
                $row->asset_code,
                $context,
                $options,
                $existing,
                $codeMap,
                (int) $row->line,
                (string) $batch->original_name,
                $row->accountable_override !== null ? (int) $row->accountable_override : null
            );

            $updates[] = [
                'batch_id'            => $id,
                'line'                => (int) $row->line,
                'status'              => $plan['status'],
                'action'              => $plan['action'],
                'property_id_no'      => $plan['property_id_no'],
                'inventory_id_no'     => $plan['inventory_id_no'],
                'resolved'            => json_encode($plan['resolved'], JSON_UNESCAPED_UNICODE),
                'issues'              => json_encode($plan['issues'], JSON_UNESCAPED_UNICODE),
                'department_id'       => $plan['department_id'],
                'accountable_name'    => $plan['accountable_name'],
                'accountable_user_id' => $plan['accountable_user_id'],
                'updated_at'          => now(),
            ];
        }

        foreach (array_chunk($updates, 250) as $chunk) {
            DB::table('asset_import_rows')->upsert(
                $chunk,
                ['batch_id', 'line'],
                ['status', 'action', 'property_id_no', 'inventory_id_no', 'resolved', 'issues', 'department_id', 'accountable_name', 'accountable_user_id', 'updated_at']
            );
        }

        self::flagRepeatedProperties($id);

        $rowsChecked = (int) DB::table('asset_import_rows')->where('batch_id', $id)->count();

        DB::table('asset_import_batches')->where('id', $id)->update([
            'rows_checked' => $rowsChecked,
            'row_count'    => $rowsChecked,
            'status'       => 'ready',
            'updated_at'   => now(),
        ]);

        return ['ok' => true, 'message' => 'Re-checked ' . $rowsChecked . ' line(s).'];
    }

    /**
     * Case E: the same property number twice in one file.
     *
     * Both lines are blocked, including a later window's line colliding with one
     * already staged, and neither is imported until the office fixes the file.
     */
    private static function flagRepeatedProperties(int $id): void
    {
        $duplicates = DB::table('asset_import_rows')
            ->where('batch_id', $id)
            ->whereNotNull('property_id_no')
            ->where('property_id_no', '<>', '')
            ->selectRaw('LOWER(property_id_no) as identifier, COUNT(*) as total')
            ->groupByRaw('LOWER(property_id_no)')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->map(static fn ($row) => mb_strtolower((string) $row->identifier))
            ->values()
            ->all();

        if (empty($duplicates)) {
            return;
        }

        $rows = DB::table('asset_import_rows')
            ->where('batch_id', $id)
            ->whereRaw('LOWER(property_id_no) IN (' . implode(',', array_fill(0, count($duplicates), '?')) . ')', $duplicates)
            ->where('status', '<>', self::IMPORTED)
            ->get(['id', 'line', 'issues', 'status']);

        foreach ($rows as $row) {
            $issues = json_decode((string) $row->issues, true) ?: [];
            $issues = array_values(array_filter($issues, fn ($issue) => ! str_contains((string) ($issue['text'] ?? ''), 'appears more than once')));

            $issues[] = [
                'level' => 'error',
                'text'  => 'This Property ID No. appears more than once in the file. Every line using it is held until the duplicate is corrected.',
            ];

            DB::table('asset_import_rows')->where('id', $row->id)->update([
                'status'     => self::BLOCKED,
                'action'     => null,
                'issues'     => json_encode($issues, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
        }
    }

    /* ═══════════════════════════ writing ═══════════════════════════ */

    /** @return bool true when an asset was created */
    private static function importRow(object $row, object $batch, ?User $actor): bool
    {
        $payload = json_decode((string) $row->payload, true) ?: [];
        $resolved = json_decode((string) $row->resolved, true) ?: [];

        $code = AssetCode::normalize((string) $row->asset_code);
        $propertyId = trim((string) $row->property_id_no);

        if ($code === '' || ! AssetCode::isValid($code)) {
            self::failRow($row, 'The asset code is missing or unusable, so nothing was written.');

            return false;
        }

        // The identity guards run again, immediately before writing. A row
        // checked an hour ago may have been imported by someone else since.
        if ($propertyId !== '' && DB::table('assets')->whereRaw('LOWER(property_id_no) = ?', [mb_strtolower($propertyId)])->exists()) {
            self::skipRow($row, 'This Property ID No. was registered in NU Trace after the file was checked. Skipped; nothing was overwritten.');

            return false;
        }

        if (AssetCode::exists($code)) {
            self::failRow($row, 'This asset code was taken by another asset after the file was checked. Nothing was written; give this line a different code and import again.');

            return false;
        }

        $name = mb_substr(trim((string) ($payload['name'] ?? '')), 0, 150);

        if ($name === '') {
            self::failRow($row, 'The asset has no description, so it was not written.');

            return false;
        }

        try {
            $assetId = DB::transaction(function () use ($row, $batch, $actor, $payload, $resolved, $code, $propertyId, $name) {
                $asset = Asset::create([
                    'user_id'           => $row->accountable_user_id ? (int) $row->accountable_user_id : null,
                    'Asset_code'        => $code,
                    'Asset_name'        => $name,
                    'Category'          => $resolved['category'] ?? 'Low value Asset',
                    'Condition'         => 'Existing',
                    'Lifecycle_Status'  => 'Active',
                    'accusion_date'     => $resolved['date'] ?? null,
                    'accusion_cost'     => null,
                    'purchase_Price'    => $resolved['unit_cost'] ?? null,
                    'warranty_months'   => null,
                    'supplier'          => null,
                    'model'             => null,
                    'manufacture'       => null,
                    'serial_Number'     => null,
                    'asset_location'    => mb_substr((string) ($payload['location'] ?? ''), 0, 255) ?: null,
                    'qr_code_path'      => null,
                    'lifespan_months'   => null,
                    'expiration_date'   => null,
                    'repair_counts'     => 0,
                    'last_maintenance_date' => null,
                    'next_maintenance_date' => null,
                    'maintenance_interval'  => null,
                    'property_id_no'    => $propertyId !== '' ? mb_substr($propertyId, 0, 100) : null,
                    'inventory_id_no'   => $row->inventory_id_no ? mb_substr((string) $row->inventory_id_no, 0, 100) : null,
                    'po_no'             => mb_substr((string) ($payload['po_no'] ?? ''), 0, 100) ?: null,
                    'accountable_name'  => $row->accountable_name ? mb_substr((string) $row->accountable_name, 0, 150) : null,
                    'department_id'     => $row->department_id ? (int) $row->department_id : null,
                    'completion_status' => self::completionStatus($resolved, $row),
                ]);

                if ($row->accountable_user_id) {
                    DB::table('asset_accountability')->insert([
                        'asset_id'    => $asset->id,
                        'user_id'     => (int) $row->accountable_user_id,
                        'request_id'  => null,
                        'Assign_date' => now(),
                        'Is_Current'  => 1,
                        'notes'       => 'Imported from ' . mb_substr((string) $batch->original_name, 0, 120) . ', line ' . $row->line,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                }

                self::audit($actor, 'CREATE',
                    'Imported ' . $code . ' — ' . $name . ' (Property ID No. ' . ($propertyId ?: '—') . ', ' . mb_substr((string) $batch->original_name, 0, 80) . ' line ' . $row->line . ').',
                    'Asset imported from the existing inventory registry',
                    $asset->id);

                return (int) $asset->id;
            });
        } catch (Throwable $e) {
            // A unique index is a race lost, not a crash: the other writer's
            // asset is the one that exists, so this line is skipped.
            if (self::isUniqueViolation($e)) {
                self::skipRow($row, 'Another asset with this property number or code was written at the same moment. Skipped to avoid a duplicate.');

                return false;
            }

            report($e);
            self::failRow($row, 'The database refused this line: ' . mb_substr($e->getMessage(), 0, 300));

            return false;
        }

        // The sticker is made after the asset exists. A storage outage must not
        // undo a saved asset; the row remembers that its image is missing and
        // can be retried without writing the asset again.
        $qrPath = null;

        try {
            $qrPath = AssetQr::saveSticker($assetId, 300);
        } catch (Throwable $e) {
            report($e);
        }

        DB::table('asset_import_rows')->where('id', $row->id)->update([
            'status'     => self::IMPORTED,
            'asset_id'   => $assetId,
            'qr_status'  => $qrPath ? 'generated' : 'failed',
            'message'    => $qrPath ? null : 'The asset is saved, but its sticker image could not be produced. Retry the stickers when storage is reachable.',
            'updated_at' => now(),
        ]);

        return true;
    }

    private static function completionStatus(array $resolved, object $row): string
    {
        // A line imported without its Property ID No. is accepted on the
        // office's instruction, but its identity is still incomplete: it has no
        // duplicate key and cannot be matched by one later.
        $missing = empty($resolved['location'])
            || empty($resolved['date'])
            || empty($resolved['category'])
            || empty($row->property_id_no)
            || empty($row->department_id)
            || empty($row->accountable_user_id);

        $issues = json_decode((string) $row->issues, true) ?: [];

        foreach ($issues as $issue) {
            if (($issue['level'] ?? '') === 'warning') {
                return $missing ? 'Needs completion' : 'Under review';
            }
        }

        return $missing ? 'Needs completion' : 'Complete';
    }

    private static function failRow(object $row, string $message): void
    {
        DB::table('asset_import_rows')->where('id', $row->id)->update([
            'status'     => self::FAILED,
            'message'    => $message,
            'updated_at' => now(),
        ]);
    }

    private static function skipRow(object $row, string $message): void
    {
        DB::table('asset_import_rows')->where('id', $row->id)->update([
            'status'     => self::EXISTS,
            'action'     => 'skip',
            'message'    => $message,
            'updated_at' => now(),
        ]);
    }

    private static function isUniqueViolation(Throwable $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'duplicate key')
            || str_contains($message, 'UNIQUE constraint failed')
            || str_contains($message, '23505');
    }

    /* ═══════════════════════════ lookups ═══════════════════════════ */

    /** Departments, employees and their accounts, loaded once per request. */
    private static function context(): array
    {
        $departments = [];

        foreach (DB::table('departments')->orderBy('Name')->get(['id', 'Name', 'status']) as $department) {
            $departments[] = [
                'id'     => (int) $department->id,
                'name'   => (string) $department->Name,
                'key'    => self::departmentKey((string) $department->Name),
                'active' => strtolower((string) $department->status) === 'active',
            ];
        }

        $employees = [];
        $byKey = [];

        $rows = DB::table('employee_numbers')
            ->leftJoin('users', 'users.employee_numbers_id', '=', 'employee_numbers.id')
            ->orderBy('employee_numbers.Full_Name')
            ->get([
                'employee_numbers.id',
                'employee_numbers.Full_Name',
                'employee_numbers.Employee_number',
                'employee_numbers.Department_id',
                'employee_numbers.status as employee_status',
                'users.id as user_id',
                'users.status as user_status',
            ]);

        foreach ($rows as $record) {
            $id = (int) $record->id;

            if (! isset($employees[$id])) {
                $employees[$id] = [
                    'id'         => $id,
                    'name'       => (string) $record->Full_Name,
                    'number'     => (string) $record->Employee_number,
                    'department_id' => $record->Department_id ? (int) $record->Department_id : null,
                    'active'     => strtolower((string) $record->employee_status) === 'active',
                    'users'      => [],
                ];

                $byKey[self::personKey((string) $record->Full_Name)][] = $id;
            }

            if ($record->user_id) {
                $employees[$id]['users'][] = [
                    'id'     => (int) $record->user_id,
                    'active' => strtolower((string) $record->user_status) === 'active',
                ];
            }
        }

        return ['departments' => $departments, 'employees' => $employees, 'people' => $byKey];
    }

    /** Existing assets keyed by property number and by ID NO. */
    private static function existingCache(): array
    {
        return [
            'property'  => self::existingProperties(null),
            'inventory' => self::existingInventory(null),
            'codes'     => AssetCode::taken(DB::table('assets')->pluck('Asset_code')->all()),
        ];
    }

    private static function existingProperties(?array $keys): array
    {
        $query = DB::table('assets')
            ->whereNotNull('property_id_no')
            ->where('property_id_no', '<>', '');

        if ($keys !== null) {
            $query->whereRaw('LOWER(property_id_no) IN (' . implode(',', array_fill(0, count($keys), '?')) . ')', $keys);
        }

        $found = [];

        foreach ($query->get(['id', 'Asset_code', 'Asset_name', 'property_id_no', 'inventory_id_no']) as $asset) {
            $found[self::identifierKey((string) $asset->property_id_no)] = [
                'id'               => (int) $asset->id,
                'code'             => (string) $asset->Asset_code,
                'name'             => (string) $asset->Asset_name,
                'property_id_no'   => (string) $asset->property_id_no,
                'inventory_id_no'  => (string) $asset->inventory_id_no,
            ];
        }

        return $found;
    }

    private static function existingInventory(?array $keys): array
    {
        $query = DB::table('assets')
            ->whereNotNull('inventory_id_no')
            ->where('inventory_id_no', '<>', '');

        if ($keys !== null) {
            $query->whereRaw('LOWER(inventory_id_no) IN (' . implode(',', array_fill(0, count($keys), '?')) . ')', $keys);
        }

        $found = [];

        foreach ($query->get(['id', 'Asset_code', 'Asset_name', 'property_id_no', 'inventory_id_no']) as $asset) {
            $found[self::identifierKey((string) $asset->inventory_id_no)] = [
                'id'             => (int) $asset->id,
                'code'           => (string) $asset->Asset_code,
                'name'           => (string) $asset->Asset_name,
                'property_id_no' => (string) $asset->property_id_no,
                'inventory_id_no' => (string) $asset->inventory_id_no,
            ];
        }

        return $found;
    }

    private static function resolveDepartment(string $source, array $context, array $options): array
    {
        $source = trim($source);
        $key = self::departmentKey($source);

        $decisions = (array) ($options['departments'] ?? []);

        if (array_key_exists($key, $decisions)) {
            $chosen = $decisions[$key];

            if ($chosen === '' || $chosen === null) {
                return ['id' => null, 'name' => $source, 'status' => 'none', 'suggested' => null];
            }

            foreach ($context['departments'] as $department) {
                if ($department['id'] === (int) $chosen) {
                    return ['id' => $department['id'], 'name' => $department['name'], 'status' => 'chosen', 'suggested' => null];
                }
            }
        }

        if ($source === '') {
            return ['id' => null, 'name' => '', 'status' => 'unmatched', 'suggested' => null];
        }

        foreach ($context['departments'] as $department) {
            if ($department['key'] === $key) {
                return ['id' => $department['id'], 'name' => $department['name'], 'status' => 'matched', 'suggested' => null];
            }
        }

        // A near match is offered, never applied: departments steer access and
        // reports, so the office confirms which one is meant.
        $possible = [];

        foreach ($context['departments'] as $department) {
            $candidate = $department['key'];

            if ($candidate === '' || $key === '') {
                continue;
            }

            if (str_contains($candidate, $key) || str_contains($key, $candidate)) {
                $possible[] = $department;
            }
        }

        if (count($possible) === 1) {
            return ['id' => null, 'name' => $source, 'status' => 'possible', 'suggested' => $possible[0]['id']];
        }

        return ['id' => null, 'name' => $source, 'status' => 'unmatched', 'suggested' => null];
    }

    private static function resolveCategory(string $source, array $options): array
    {
        $source = trim($source);
        $key = self::categoryKey($source);

        $decisions = (array) ($options['categories'] ?? []);

        if ($key !== '' && array_key_exists($key, $decisions)) {
            $chosen = self::canonicalCategory((string) $decisions[$key]);

            if ($chosen) {
                return ['value' => $chosen, 'status' => 'chosen'];
            }
        }

        $chosen = self::canonicalCategory($source);

        if ($chosen) {
            return ['value' => $chosen, 'status' => 'matched'];
        }

        return ['value' => null, 'status' => 'unmatched'];
    }

    private static function canonicalCategory(string $source): ?string
    {
        $key = self::categoryKey($source);

        if ($key === '') {
            return null;
        }

        foreach (self::CATEGORY_ALIASES as $category => $aliases) {
            if ($key === self::categoryKey($category)) {
                return $category;
            }

            foreach ($aliases as $alias) {
                if ($key === self::categoryKey($alias)) {
                    return $category;
                }
            }
        }

        return null;
    }

    private static function resolvePerson(string $source, array $context, array $options): array
    {
        $source = trim($source);
        $key = self::personKey($source);

        $unassigned = [
            'status'     => self::PERSON_UNASSIGNED,
            'user_id'    => null,
            'employee_id' => null,
            'display'    => $source,
            'candidates' => [],
        ];

        if ($key === '') {
            return $unassigned;
        }

        $decisions = (array) ($options['people'] ?? []);

        // An explicit decision wins: a chosen account, or an explicit "leave
        // unassigned" which is stored as an empty string.
        if (array_key_exists($key, $decisions)) {
            $chosen = $decisions[$key];

            if ($chosen === '' || $chosen === null) {
                return $unassigned;
            }

            foreach ($context['employees'] as $employee) {
                foreach ($employee['users'] as $user) {
                    if ($user['id'] === (int) $chosen && $user['active']) {
                        return [
                            'status'      => self::PERSON_ASSIGNED,
                            'user_id'     => $user['id'],
                            'employee_id' => $employee['id'],
                            'display'     => $employee['name'] . ' (' . $employee['number'] . ')',
                            'candidates'  => [],
                        ];
                    }
                }
            }

            return $unassigned;
        }

        $ids = $context['people'][$key] ?? [];

        if (count($ids) === 0) {
            return [
                'status'     => self::PERSON_NO_EMPLOYEE,
                'user_id'    => null,
                'employee_id' => null,
                'display'    => $source,
                'candidates' => [],
            ];
        }

        if (count($ids) > 1) {
            $candidates = [];

            foreach ($ids as $employeeId) {
                $employee = $context['employees'][$employeeId];
                $candidates[] = ['id' => $employeeId, 'label' => $employee['name'] . ' (' . $employee['number'] . ')'];
            }

            return [
                'status'      => self::PERSON_AMBIGUOUS,
                'user_id'     => null,
                'employee_id' => null,
                'display'     => $source,
                'candidates'  => $candidates,
            ];
        }

        $employee = $context['employees'][$ids[0]];
        $display = $employee['name'] . ' (' . $employee['number'] . ')';

        if (! $employee['active']) {
            return [
                'status'      => self::PERSON_INACTIVE_EMPLOYEE,
                'user_id'     => null,
                'employee_id' => $employee['id'],
                'display'     => $display,
                'candidates'  => [],
            ];
        }

        if (count($employee['users']) === 0) {
            return [
                'status'      => self::PERSON_NO_ACCOUNT,
                'user_id'     => null,
                'employee_id' => $employee['id'],
                'display'     => $display,
                'candidates'  => [],
            ];
        }

        $active = array_values(array_filter($employee['users'], static fn ($user) => $user['active']));

        if (count($active) !== 1) {
            return [
                'status'      => $active === [] ? self::PERSON_INACTIVE_ACCOUNT : self::PERSON_AMBIGUOUS,
                'user_id'     => null,
                'employee_id' => $employee['id'],
                'display'     => $display,
                'candidates'  => array_map(static fn ($user) => ['id' => $user['id'], 'label' => $display], $employee['users']),
            ];
        }

        return [
            'status'      => self::PERSON_ASSIGNED,
            'user_id'     => $active[0]['id'],
            'employee_id' => $employee['id'],
            'display'     => $display,
            'candidates'  => [],
        ];
    }

    /**
     * The accountable person exactly as the office chose it for one line.
     *
     * `0` means "left unassigned on purpose" — the name from the file is still
     * shown, so nothing the office wrote down is lost. A positive value is a
     * user account they picked; the account is re-checked here because a batch
     * may sit open for days, and an account can be deactivated in the meantime.
     */
    private static function overridePerson(int $userId, string $source, array $context): array
    {
        if ($userId <= 0) {
            return [
                'status'      => self::PERSON_UNASSIGNED,
                'user_id'     => null,
                'employee_id' => null,
                'display'     => $source,
                'candidates'  => [],
            ];
        }

        foreach ($context['employees'] as $employee) {
            foreach ($employee['users'] as $user) {
                if ((int) $user['id'] !== $userId) {
                    continue;
                }

                $active = $employee['active'] && $user['active'];

                return [
                    'status'      => $active ? self::PERSON_ASSIGNED : self::PERSON_INACTIVE_ACCOUNT,
                    'user_id'     => $active ? $userId : null,
                    'employee_id' => $employee['id'],
                    'display'     => $employee['name'] . ' (' . $employee['number'] . ')',
                    'candidates'  => [],
                    // Stored on the asset, so the holder's name is the one that
                    // was chosen rather than a name from the spreadsheet.
                    'name'        => $employee['name'],
                ];
            }
        }

        // The account vanished between choosing it and re-checking the line:
        // fall back to what the file says instead of failing the import.
        return self::resolvePerson($source, $context, []);
    }

    /* ═══════════════════════════ rows ═══════════════════════════ */

    private static function rowPayload(object $row): array
    {
        $payload = json_decode((string) $row->payload, true) ?: [];
        $resolved = json_decode((string) $row->resolved, true) ?: [];

        return [
            'line'                => (int) $row->line,
            'status'              => $row->status,
            'action'              => $row->action,
            'asset_code'          => $row->asset_code,
            'property_id_no'      => $row->property_id_no,
            'inventory_id_no'     => $row->inventory_id_no,
            'po_no'               => $payload['po_no'] ?? '',
            'name'                => $payload['name'] ?? '',
            'date'                => $resolved['date'] ?? ($payload['accusion_date'] ?? ''),
            'location'            => $payload['location'] ?? '',
            'department'          => $payload['department'] ?? '',
            'department_status'   => $resolved['department_status'] ?? 'unmatched',
            'department_id'       => $row->department_id ? (int) $row->department_id : null,
            'department_name'     => $resolved['department_name'] ?? '',
            'department_suggested' => $resolved['department_suggested'] ?? null,
            'category'            => $resolved['category'] ?? null,
            'category_raw'        => $payload['category'] ?? '',
            'accountable_name'    => $row->accountable_name,
            'accountable_override' => $row->accountable_override !== null ? (int) $row->accountable_override : null,
            'accountable_status'  => $resolved['accountable_status'] ?? 'n/a',
            'accountable_display' => $resolved['accountable_display'] ?? '',
            'accountable_user_id' => $row->accountable_user_id ? (int) $row->accountable_user_id : null,
            'unit_cost'           => $resolved['unit_cost'] ?? null,
            'issues'              => json_decode((string) $row->issues, true) ?: [],
            'asset_id'            => $row->asset_id ? (int) $row->asset_id : null,
            'qr_status'           => $row->qr_status,
            'message'             => $row->message,
        ];
    }

    private static function payloadFromCells(array $cells, array $mapping): array
    {
        $payload = [];

        foreach (self::FIELDS as $field => $definition) {
            $index = $mapping[$field] ?? null;
            $value = $index === null ? '' : trim((string) ($cells[(int) $index] ?? ''));

            $payload[$field] = $value;
        }

        return $payload;
    }

    /* ═══════════════════════════ small helpers ═══════════════════════════ */

    public static function parseDate(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            [$year, $month, $day] = array_map('intval', explode('-', $value));

            return checkdate($month, $day, $year) ? $value : null;
        }

        // A serial number, in a CSV exported from Excel.
        if (preg_match('/^\d{1,5}(\.\d+)?$/', $value) && (float) $value >= 20000 && (float) $value <= 60000) {
            return SpreadsheetReader::excelDate((float) $value);
        }

        $formats = ['d-M-y', 'j-M-y', 'd-M-Y', 'j-M-Y', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'Y/m/d', 'm/d/Y', 'F j, Y', 'M j, Y', 'j F Y', 'd M Y', 'Y-m-d H:i:s', 'd/m/y', 'm/d/y'];

        foreach ($formats as $format) {
            $date = DateTime::createFromFormat('!' . $format, $value);
            $errors = DateTime::getLastErrors();

            if ($date && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                $year = (int) $date->format('Y');

                if ($year >= 1900 && $year <= 2100) {
                    return $date->format('Y-m-d');
                }
            }
        }

        try {
            $parsed = Carbon::parse($value);

            // Carbon happily reads "5" as a time; only a written date is used.
            if (preg_match('/[A-Za-z]|\d{1,4}[-\/.]\d{1,2}[-\/.]\d{1,4}/', $value)) {
                return $parsed->format('Y-m-d');
            }
        } catch (Throwable $e) {
            return null;
        }

        return null;
    }

    private static function parseCost(string $value): ?float
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $clean = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', $value));

        if ($clean === '' || $clean === null || ! is_numeric($clean)) {
            return null;
        }

        return round((float) $clean, 2);
    }

    private static function headingKey(string $heading): string
    {
        $heading = mb_strtolower(trim($heading));
        $heading = str_replace(['&', "\u{00A0}"], ' and ', $heading);
        $heading = preg_replace('/[^a-z0-9]+/', ' ', $heading) ?? '';
        $heading = preg_replace('/\s+/', ' ', $heading) ?? '';

        return trim($heading);
    }

    private static function departmentKey(string $name): string
    {
        return self::headingKey($name);
    }

    private static function categoryKey(string $name): string
    {
        return self::headingKey($name);
    }

    /**
     * A name that compares the same however the office wrote it.
     *
     * Tokens are sorted, so "Atienza, Rio" and "Rio Atienza" meet, and
     * "Maria Andrea Magaling" and "Magaling, Maria Andrea" do too.
     */
    private static function personKey(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = preg_replace('/[.,]/', ' ', $name) ?? $name;
        $tokens = preg_split('/\s+/', $name) ?: [];
        $tokens = array_values(array_filter($tokens, static fn ($token) => $token !== ''));

        if ($tokens === []) {
            return '';
        }

        sort($tokens);

        return implode(' ', $tokens);
    }

    private static function identifierKey(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    private static function directory(int $batchId): string
    {
        return 'asset-imports/' . $batchId;
    }

    private static function sourcePath(object $batch): ?string
    {
        if (! $batch->disk_path) {
            return null;
        }

        return Storage::disk('local')->exists($batch->disk_path)
            ? Storage::disk('local')->path($batch->disk_path)
            : null;
    }

    private static function removeFiles(?object $batch): void
    {
        if (! $batch) {
            return;
        }

        try {
            Storage::disk('local')->deleteDirectory(self::directory((int) $batch->id));
        } catch (Throwable $e) {
            // Files left behind are collected by pruneStale() later; failing to
            // clean up must never fail an import that already succeeded.
            Log::warning('Could not remove import files for batch ' . $batch->id . ': ' . $e->getMessage());
        }
    }

    /** Remove staging directories of imports that were abandoned. */
    public static function pruneStale(): void
    {
        try {
            $disk = Storage::disk('local');

            if (! $disk->exists('asset-imports')) {
                return;
            }

            $hours = max(1, (int) config('asset_import.stale_hours', 48));
            $cutoff = now()->subHours($hours)->timestamp;

            // Parts of an upload that was abandoned halfway: nothing will ever
            // join them, so they are only taking up disk.
            if ($disk->exists('asset-imports/_parts')) {
                foreach ($disk->directories('asset-imports/_parts') as $directory) {
                    $newest = 0;

                    foreach ($disk->allFiles($directory) as $file) {
                        $newest = max($newest, (int) $disk->lastModified($file));
                    }

                    if (($newest > 0 ? $newest : (int) $disk->lastModified($directory)) < $cutoff) {
                        $disk->deleteDirectory($directory);
                    }
                }
            }

            foreach ($disk->directories('asset-imports') as $directory) {
                $id = (int) basename($directory);

                if (in_array($id, [0], true)) {
                    continue;
                }

                $active = DB::table('asset_import_batches')
                    ->where('id', $id)
                    ->whereIn('status', ['uploaded', 'checking', 'ready', 'importing'])
                    ->exists();

                if ($active) {
                    continue;
                }

                $files = $disk->files($directory);
                $newest = 0;

                foreach ($files as $file) {
                    $newest = max($newest, (int) $disk->lastModified($file));
                }

                if ($newest > 0 ? $newest < $cutoff : $disk->lastModified($directory) < $cutoff) {
                    $disk->deleteDirectory($directory);
                }
            }
        } catch (Throwable $e) {
            Log::warning('Import staging cleanup failed: ' . $e->getMessage());
        }
    }

    private static function optionsFor(object $batch): array
    {
        $options = json_decode((string) $batch->options, true) ?: [];

        return [
            'departments'              => (array) ($options['departments'] ?? []),
            'categories'               => (array) ($options['categories'] ?? []),
            'people'                   => (array) ($options['people'] ?? []),
            'allow_missing_department' => (bool) ($options['allow_missing_department'] ?? false),
            'allow_blank_property'     => (bool) ($options['allow_blank_property'] ?? false),
        ];
    }

    /**
     * Keep only source-value => value pairs, keyed exactly the way the lookup
     * that reads them keys its candidates. A decision stored under a different
     * spelling of the same name would never be found again.
     */
    private static function cleanMap($input, string $kind): array
    {
        if (! is_array($input)) {
            return [];
        }

        $clean = [];

        foreach ($input as $source => $value) {
            $source = trim((string) $source);

            if ($source === '') {
                continue;
            }

            $key = match ($kind) {
                'category'   => self::categoryKey($source),
                'person'     => self::personKey($source),
                default      => self::departmentKey($source),
            };

            if ($key === '') {
                continue;
            }

            if (is_array($value)) {
                $value = $value['id'] ?? null;
            }

            $clean[$key] = is_string($value) ? trim($value) : $value;
        }

        return $clean;
    }

    private static function audit(?User $actor, string $type, string $notes, string $description, ?int $assetId = null): void
    {
        try {
            DB::table('audit_logs')->insert([
                'user_id'            => $actor?->id,
                'request_id'         => null,
                'asset_id'           => $assetId,
                'action_type'        => $type,
                'notes'              => $notes,
                'action_description' => $description,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Inventory import audit entry failed: ' . $e->getMessage());
        }
    }

    private static function humanSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }

        return max(1, (int) round($bytes / 1024)) . ' KB';
    }
}
