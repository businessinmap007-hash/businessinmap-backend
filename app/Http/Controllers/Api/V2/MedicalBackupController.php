<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The encrypted backup of a patient's medical file (kept on his phone). One blob per account, replaced on every
 * backup. The server only checks that it LOOKS like what the app writes — it has no way to open it.
 */
final class MedicalBackupController extends Controller
{
    private const MAX_BYTES = 600_000;

    /** GET /api/v2/medical-backup — the account's blob, or 404 when none was ever made. */
    public function show(Request $request)
    {
        $row = DB::table('medical_backups')->where('user_id', $request->user()->id)->first();
        abort_unless((bool) $row, 404, __('لا توجد نسخة احتياطية.'));

        return response()->json(['success' => true, 'data' => [
            'blob' => $row->blob,
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
        DB::table('medical_backups')->updateOrInsert(
            ['user_id' => $request->user()->id],
            ['blob' => $data['blob'], 'updated_at' => $now] + (DB::table('medical_backups')->where('user_id', $request->user()->id)->exists() ? [] : ['created_at' => $now])
        );

        return response()->json(['success' => true, 'data' => ['updated_at' => $now->toIso8601String()]]);
    }

    /** DELETE /api/v2/medical-backup */
    public function destroy(Request $request)
    {
        DB::table('medical_backups')->where('user_id', $request->user()->id)->delete();

        return response()->json(['success' => true]);
    }
}
