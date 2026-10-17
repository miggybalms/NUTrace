<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Supporting documents attached to a disposal request.
 *
 * Everything here is private. Files go to the local private disk — never the
 * public media bucket — and are served only through an authorized route that
 * checks the caller is the Asset Management Office. A document URL is not
 * something that can be shared outside the system.
 *
 * The scanned signed official form is NOT stored here: it belongs to
 * disposal_forms and is recorded by DisposalForms::recordSigned(). This class is
 * for the rest of the documentation a disposal needs.
 */
class DisposalDocuments
{
    /** The private disk. The media disk is public and must not hold these. */
    public const DISK = 'local';
    public const FOLDER = 'disposal-requests/documents';

    /** Accepted formats and the size limit, matching the signed-form scan. */
    public const MIMES = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
    public const MAX_KB = 5120;

    /** Longest "what is this document" label. */
    public const TITLE_MAX = 150;

    private static ?bool $ready = null;

    /** Has the disposal_documents migration been run? */
    public static function ready(): bool
    {
        if (self::$ready === null) {
            self::$ready = Schema::hasTable('disposal_documents');
        }

        return self::$ready;
    }

    /**
     * Every document attached to a request, newest first.
     *
     * @return Collection<int, object>
     */
    public static function forRequest(int $requestId): Collection
    {
        if (! self::ready() || $requestId <= 0) {
            return new Collection();
        }

        $rows = DB::table('disposal_documents')
            ->leftJoin('assets', 'disposal_documents.asset_id', '=', 'assets.id')
            ->leftJoin('users as uploader', 'disposal_documents.uploaded_by', '=', 'uploader.id')
            ->leftJoin('employee_numbers as uploader_emp', 'uploader.employee_numbers_id', '=', 'uploader_emp.id')
            ->where('disposal_documents.request_id', $requestId)
            ->orderByDesc('disposal_documents.created_at')
            ->orderByDesc('disposal_documents.id')
            ->get([
                'disposal_documents.*',
                'assets.Asset_code as asset_code',
                DB::raw('uploader_emp."Full_Name" as uploader_name'),
                'uploader.email as uploader_email',
            ]);

        return $rows->map(function ($row) {
            $row->size_label = self::sizeLabel($row->file_size ?? null);
            $row->uploaded_label = $row->created_at
                ? Carbon::parse($row->created_at)->format('M j, Y g:i A')
                : '—';

            return $row;
        });
    }

    /** One document row, or null when it does not exist. */
    public static function find(int $id): ?object
    {
        if (! self::ready()) {
            return null;
        }

        return DB::table('disposal_documents')->where('id', $id)->first();
    }

    /**
     * Attach a document to a disposal request.
     *
     * The file is written first and the row second; if the row cannot be
     * written the stored object is removed again, so a failed upload never
     * leaves an orphaned file or a row pointing at nothing.
     *
     * @return array{ok: bool, message: string, id?: int, duplicate?: bool}
     */
    public static function store(
        int $requestId,
        ?int $assetId,
        ?string $title,
        UploadedFile $file,
        ?User $actor = null
    ): array {
        if (! self::ready()) {
            return ['ok' => false, 'message' => 'The disposal documents table has not been created yet — run the database migration first.'];
        }

        $request = DB::table('requests')->where('id', $requestId)->first();

        if (! $request) {
            return ['ok' => false, 'message' => 'That disposal request no longer exists.'];
        }

        if (strtolower((string) $request->request_type) !== 'disposal') {
            return ['ok' => false, 'message' => 'Supporting documents can only be attached to a disposal request.'];
        }

        if ($assetId !== null && ! self::assetBelongsToRequest($request, $assetId)) {
            return ['ok' => false, 'message' => 'The selected asset is not part of this disposal request.'];
        }

        $title = trim((string) $title);
        $title = $title === '' ? null : Str::limit($title, self::TITLE_MAX, '');

        $original = Str::limit(trim($file->getClientOriginalName()), 240, '');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $storedName = 'disposal-' . $requestId . '-' . Str::random(20) . '.' . $extension;

        try {
            $path = Storage::disk(self::DISK)->putFileAs(self::FOLDER, $file, $storedName);
        } catch (Throwable $e) {
            Log::error('Storing a disposal document for request #' . $requestId . ' failed: ' . $e->getMessage());

            return ['ok' => false, 'message' => 'The document could not be stored. Nothing was attached — please try again.'];
        }

        if (! is_string($path) || $path === '') {
            return ['ok' => false, 'message' => 'The document could not be stored. Nothing was attached — please try again.'];
        }

        try {
            $id = (int) DB::table('disposal_documents')->insertGetId([
                'request_id'  => $requestId,
                'form_id'     => self::formIdFor($requestId),
                'asset_id'    => $assetId,
                'title'       => $title,
                'file_name'   => $original,
                'file_path'   => $path,
                'file_size'   => $file->getSize(),
                'mime'        => Str::limit((string) ($file->getClientMimeType() ?: $file->getMimeType() ?: 'application/octet-stream'), 120, ''),
                'uploaded_by' => $actor?->id,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        } catch (Throwable $e) {
            // The file is already on disk; remove it rather than leave an
            // unlisted object there.
            Storage::disk(self::DISK)->delete($path);

            Log::error('Recording a disposal document for request #' . $requestId . ' failed: ' . $e->getMessage());

            return ['ok' => false, 'message' => 'The document could not be attached. Nothing was changed — please try again.'];
        }

        self::audit(
            $actor,
            $requestId,
            $assetId,
            'Attached supporting document "' . ($title ?: $original) . '" to disposal request #' . $requestId,
            'Supporting document attached to disposal request #' . $requestId . ' by ' . self::actorName($actor) . '.'
        );

        return [
            'ok'      => true,
            'id'      => $id,
            'message' => '“' . ($title ?: $original) . '” was attached to the disposal request.',
        ];
    }

    /**
     * Remove a supporting document.
     *
     * Refused once the disposal has been finalized: the documentation is part
     * of the historical record at that point. Removing a document before then
     * leaves an audit entry saying what was removed and by whom — the file
     * itself is the only thing that goes.
     *
     * @return array{ok: bool, message: string}
     */
    public static function delete(int $id, ?User $actor = null): array
    {
        if (! self::ready()) {
            return ['ok' => false, 'message' => 'The disposal documents table has not been created yet — run the database migration first.'];
        }

        $document = self::find($id);

        if (! $document) {
            return ['ok' => false, 'message' => 'That document no longer exists.'];
        }

        if (self::lockReason((int) $document->request_id) !== null) {
            return ['ok' => false, 'message' => self::lockReason((int) $document->request_id)];
        }

        DB::table('disposal_documents')->where('id', $id)->delete();

        if (! empty($document->file_path)) {
            Storage::disk(self::DISK)->delete($document->file_path);
        }

        $name = trim((string) ($document->title ?? '')) ?: $document->file_name;

        self::audit(
            $actor,
            (int) $document->request_id,
            $document->asset_id ? (int) $document->asset_id : null,
            'Removed supporting document "' . $name . '" from disposal request #' . $document->request_id,
            'Supporting document removed from disposal request #' . $document->request_id . ' by ' . self::actorName($actor) . '.'
        );

        return ['ok' => true, 'message' => '“' . $name . '” was removed.'];
    }

    /**
     * Why documents on this request can no longer be changed, or null when they
     * still can.
     *
     * A finalized disposal is history: neither the record nor its documents are
     * deleted by normal processing.
     */
    public static function lockReason(int $requestId): ?string
    {
        if (DisposalForms::tablesReady()) {
            $status = DB::table('disposal_forms')->where('request_id', $requestId)->value('status');

            if ($status !== null && DisposalForms::isFinalized((string) $status)) {
                return 'This disposal has been finalized. Its documents are part of the historical record and can no longer be removed.';
            }
        }

        if (DB::table('disposals')->where('Request_id', $requestId)->exists()) {
            return 'This disposal has already been recorded. Its documents are part of the historical record and can no longer be removed.';
        }

        return null;
    }

    /** A human size: "1.4 MB". */
    public static function sizeLabel(?int $bytes): string
    {
        $bytes = (int) $bytes;

        if ($bytes <= 0) {
            return '—';
        }

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 0) . ' KB';
        }

        return $bytes . ' B';
    }

    /* ─────────────────────────── helpers ─────────────────────────── */

    /** Does this asset belong to the request? */
    private static function assetBelongsToRequest(object $request, int $assetId): bool
    {
        $inItems = DB::table('request_items')
            ->where('request_id', $request->id)
            ->where('asset_id', $assetId)
            ->exists();

        if ($inItems) {
            return true;
        }

        return (int) ($request->asset_id ?? 0) === $assetId;
    }

    /** The form generated from this request, when one exists. */
    private static function formIdFor(int $requestId): ?int
    {
        if (! DisposalForms::tablesReady()) {
            return null;
        }

        $id = DB::table('disposal_forms')->where('request_id', $requestId)->value('id');

        return $id ? (int) $id : null;
    }

    private static function actorName(?User $actor): string
    {
        if (! $actor) {
            return 'the system';
        }

        $name = trim((string) ($actor->display_name ?? ''));

        return $name !== '' ? $name : (string) ($actor->email ?? ('user #' . $actor->id));
    }

    private static function audit(
        ?User $actor,
        int $requestId,
        ?int $assetId,
        string $notes,
        string $description
    ): void {
        try {
            DB::table('audit_logs')->insert([
                'user_id'            => $actor?->id,
                'request_id'         => $requestId,
                'asset_id'           => $assetId,
                'action_type'        => 'UPDATE',
                'notes'              => $notes,
                'action_description' => $description,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Audit log skipped on a disposal document action: ' . $e->getMessage());
        }
    }
}
