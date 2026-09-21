<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\BusinessContext;
use Illuminate\Http\Request;

/**
 * Find the client a trainer is about to write a plan for.
 *
 * The same rule as the business web panel's picker: an EXACT phone or e-mail —
 * the two things a trainer standing in front of the client can actually ask
 * for. Deliberately not a search: a partial match over every account on the
 * platform would be a people-finder, not a client picker. It is also throttled,
 * since an exact match is the one thing that could otherwise be enumerated.
 */
final class TrainerClientLookupController extends Controller
{
    /** GET /api/v2/business/training/clients/lookup?q=<phone or email> */
    public function show(Request $request)
    {
        $data = $request->validate(['q' => ['required', 'string', 'max:120']]);

        $term = trim($data['q']);

        $client = User::query()
            ->where('type', User::TYPE_CLIENT)
            // A trainer is never their own client.
            ->where('id', '!=', BusinessContext::id($request))
            ->where(fn ($w) => $w->where('phone', $term)->orWhere('email', $term))
            ->first(['id', 'name', 'phone']);

        return response()->json(['success' => true, 'data' => [
            'found' => $client !== null,
            'client' => $client ? ['id' => (int) $client->id, 'name' => (string) $client->name, 'phone' => (string) $client->phone] : null,
        ]]);
    }
}
