<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The encrypted backup of a clinic's patient files (kept on the clinic device). One blob per account, replaced on every
 * backup; the server only checks that it LOOKS like what the app writes — it has no way to open it, and nothing in it
 * is readable (even the number of patients is inside the ciphertext).
 *
 * Larger than the patient's own backup (a clinic's files are many): up to 30 MB of sealed text, which is roughly fifteen
 * thousand files once gzipped. Past that the clinic keeps the backup as a file of its own.
 */
final class ClinicFilesBackupController extends Controller
{
    public const MAX_BYTES = 30_000_000;

    /** The sealed text is a private file, never a column: megabytes in a row hit a database's packet limit. */
    private function path(int $userId): string
    {
        return 'clinic-file-backups/' . $userId . '.json';
    }

    /** GET /api/v2/clinic-files-backup — the account's blob, or 404 when none was ever made. */
    public function show(Request $request)
    {
        $row = DB::table('clinic_file_backups')->where('user_id', $request->user()->id)->first();
        abort_unless($row && Storage::disk('local')->exists($row->path), 404, __('لا توجد نسخة احتياطية.'));

        return response()->json(['success' => true, 'data' => [
            'blob' => Storage::disk('local')->get($row->path),
            'bytes' => (int) $row->bytes,
            'updated_at' => \Illuminate\Support\Carbon::parse($row->updated_at)->toIso8601String(),
        ]]);
    }

    /** PUT /api/v2/clinic-files-backup — `{blob}` replaces the account's backup. */
    public function update(Request $request)
    {
        $data = $request->validate(['blob' => ['required', 'string', 'max:' . self::MAX_BYTES]]);

        // The app writes JSON {v, kind: clinic_files, kdf, iterations, salt, data}; anything else is not ours.
        $json = json_decode($data['blob'], true);
        if (
            ! is_array($json)
            || ($json['kind'] ?? null) !== 'clinic_files'
            || ! isset($json['v'], $json['salt'], $json['data'], $json['iterations'])
            || (int) $json['iterations'] < 100_000
        ) {
            return response()->json(['success' => false, 'message' => __('هذه ليست نسخة احتياطية صالحة.')], 422);
        }

        $now = now();
        $path = $this->path((int) $request->user()->id);
        Storage::disk('local')->put($path, $data['blob']);

        $exists = DB::table('clinic_file_backups')->where('user_id', $request->user()->id)->exists();
        DB::table('clinic_file_backups')->updateOrInsert(
            ['user_id' => $request->user()->id],
            ['path' => $path, 'bytes' => strlen($data['blob']), 'updated_at' => $now] + ($exists ? [] : ['created_at' => $now])
        );

        return response()->json(['success' => true, 'data' => ['updated_at' => $now->toIso8601String(), 'bytes' => strlen($data['blob'])]]);
    }

    /** DELETE /api/v2/clinic-files-backup */
    public function destroy(Request $request)
    {
        Storage::disk('local')->delete($this->path((int) $request->user()->id));
        DB::table('clinic_file_backups')->where('user_id', $request->user()->id)->delete();

        return response()->json(['success' => true]);
    }
}
