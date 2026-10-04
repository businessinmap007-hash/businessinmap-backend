<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * «الملف الطبي على الموبايل» — the relay for showing it to a doctor or a pharmacist.
 *
 * The phone keeps the file. When the patient shares, the phone encrypts what he chose with a key it makes for
 * this one share, sends ONLY the ciphertext here, and shows a QR code holding the share id AND the key. The
 * doctor's phone scans it, fetches the ciphertext and decrypts it on the doctor's phone. The server never has
 * the key, so what it stores for those few minutes is unreadable — to us as much as to anyone.
 *
 * A share lives 15 minutes by default (60 at most); the patient can end it sooner.
 */
final class MedicalShareController extends Controller
{
    /** The ciphertext of a text medical file is small; this caps a share well above it. */
    private const MAX_CIPHERTEXT = 400_000;

    /** POST /api/v2/medical-shares — `{ciphertext, minutes?}` → `{id, expires_at}` */
    public function store(Request $request)
    {
        $data = $request->validate([
            'ciphertext' => ['required', 'string', 'max:' . self::MAX_CIPHERTEXT, 'regex:/^[A-Za-z0-9+\/=_-]+$/'],
            'minutes' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);

        // Housekeeping: expired shares are useless to everyone — drop them as new ones arrive.
        DB::table('medical_shares')->where('expires_at', '<', now())->delete();

        $uuid = (string) Str::uuid();
        $expires = now()->addMinutes((int) ($data['minutes'] ?? 15));
        DB::table('medical_shares')->insert([
            'uuid' => $uuid, 'user_id' => $request->user()->id, 'ciphertext' => $data['ciphertext'],
            'expires_at' => $expires, 'reads' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'data' => ['id' => $uuid, 'expires_at' => $expires->toIso8601String()]], 201);
    }

    /** GET /api/v2/medical-shares/{id} — the ciphertext, for whoever scanned the code (and so holds the key). */
    public function show(Request $request, string $id)
    {
        $share = DB::table('medical_shares')->where('uuid', $id)->where('expires_at', '>', now())->first();
        abort_unless((bool) $share, 404, __('انتهت صلاحية المشاركة أو أُلغيت.'));

        DB::table('medical_shares')->where('id', $share->id)->increment('reads');
        $owner = DB::table('users')->where('id', $share->user_id)->first(['name']);

        return response()->json(['success' => true, 'data' => [
            'ciphertext' => $share->ciphertext,
            'shared_by' => (string) ($owner->name ?? ''),
            'created_at' => \Illuminate\Support\Carbon::parse($share->created_at)->toIso8601String(),
            'expires_at' => \Illuminate\Support\Carbon::parse($share->expires_at)->toIso8601String(),
        ]]);
    }

    /** DELETE /api/v2/medical-shares/{id} — the patient ends his share now. */
    public function destroy(Request $request, string $id)
    {
        DB::table('medical_shares')->where('uuid', $id)->where('user_id', $request->user()->id)->delete();

        return response()->json(['success' => true]);
    }
}
