<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\FraudFlag;
use App\Services\FraudDetectionService;
use Illuminate\Http\Request;

/**
 * Suspected-fraud review — the human end of the rating-graph scan.
 *
 * Read-and-dismiss only. Acting on a flag means going to the fine screen (levy)
 * or the user screen (ban) — the scan suggests, the admin decides. USERS-gated,
 * alongside the ban it usually leads to.
 */
final class FraudFlagController extends Controller
{
    public function __construct(private readonly FraudDetectionService $detector) {}

    public function index(Request $request)
    {
        $status = (string) $request->get('status', FraudFlag::STATUS_OPEN);

        $flags = FraudFlag::query()
            ->with('user:id,name,email,phone,banned_at')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderByDesc('score')
            ->paginate(30)
            ->withQueryString();

        return view('admin-v2.fraud-flags.index', compact('flags', 'status'));
    }

    /** Run the scan on demand (it also runs daily). */
    public function scan(Request $request)
    {
        $r = $this->detector->scan();

        return back()->with('status', __('اكتمل الفحص: :flagged حساب مشبوه.', ['flagged' => $r['flagged']]));
    }

    public function dismiss(Request $request, int $flag)
    {
        $model = FraudFlag::findOrFail($flag);
        $this->detector->dismiss($model, (int) $request->user()->id);

        return back()->with('status', __('صُرف البلاغ كإيجابية كاذبة.'));
    }
}
