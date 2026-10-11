<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The encrypted backup of a patient's medical file (kept on his phone). One blob per account, replaced on every
 * backup. The server only checks that it LOOKS like what the app writes — it has no way to open it.
 */
final class MedicalBackupController extends Controller
{
    /** 5 MB of sealed text — the file holds text only, so this is years of entries; kept as a private file. */
    public const MAX_BYTES = 5_000_000;

    private function path(int $userId): string
    {
        return 'medical-backups/'.$userId.'.json';
    }

    /** GET /api/v2/medical-backup — the account's blob, or 404 when none was ever made. */
    public function show(Request $request)
    {
        $row = DB::table('medical_backups')->where('user_id', $request->user()->id)->first();
        abort_unless((bool) $row, 404, __('لا توجد نسخة احتياطية.'));

        // a backup written before the move still sits in the column until the next one replaces it
        $blob = $row->path && Storage::disk('local')->exists($row->path) ? Storage::disk('local')->get($row->path) : $row->blob;
        abort_unless($blob !== null, 404, __('لا توجد نسخة احتياطية.'));

        return response()->json(['success' => true, 'data' => [
            'blob' => $blob,
            'updated_at' => \Illuminate\Support\Carbon::parse($row->updated_at)->toIso8601String(),
        ]]);
    }

    /** PUT /api/v2/medical-backup — `{blob}` replaces the account's backup. */
    public function update(Request $request)
    {
        $data = $request->validate(['blob' => ['required', 'string', 'max:' . self::MAX_BYTES]]);

        // The app writes JSON {v, kdf, iterations, salt, data}; anything else is not a backup of ours.
        $json = json_decode($data['blob'], true);
        if (! is_array($json) || ! isset($json['v'], $json['salt'], $json['data'], $json['iterations']) || (int) $json['iterations'] < 100_000) {
            return response()->json(['success' => false, 'message' => __('هذه ليست نسخة احتياطية صالحة.')], 422);
        }

        $now = now();
        $path = $this->path((int) $request->user()->id);
        Storage::disk('local')->put($path, $data['blob']);

        $exists = DB::table('medical_backups')->where('user_id', $request->user()->id)->exists();
        DB::table('medical_backups')->updateOrInsert(
            ['user_id' => $request->user()->id],
            ['blob' => null, 'path' => $path, 'bytes' => strlen($data['blob']), 'updated_at' => $now] + ($exists ? [] : ['created_at' => $now])
        );

        return response()->json(['success' => true, 'data' => ['updated_at' => $now->toIso8601String()]]);
    }

    /** DELETE /api/v2/medical-backup */
    public function destroy(Request $request)
    {
        Storage::disk('local')->delete($this->path((int) $request->user()->id));
        DB::table('medical_backups')->where('user_id', $request->user()->id)->delete();

        return response()->json(['success' => true]);
    }
}
