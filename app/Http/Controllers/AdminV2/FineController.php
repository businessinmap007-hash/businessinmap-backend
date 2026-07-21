<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Fine;
use App\Models\User;
use App\Services\FineService;
use Illuminate\Http\Request;

/**
 * Platform fines for fraud/abuse — the unilateral penalty, outside a dispute.
 *
 * MONEY-gated, not DISPUTES: a fine here is not a ruling between two parties,
 * it is the platform taking money from one user, and the money core is what it
 * touches. Every levy freezes the wallet and opens an appeal window; nothing is
 * captured from this screen — the sweep does that once the window closes.
 */
final class FineController extends Controller
{
    public function __construct(private readonly FineService $fines) {}

    public function index(Request $request)
    {
        $status = (string) $request->get('status', '');

        $fines = Fine::query()
            ->with('user:id,name,email,phone')
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        $statuses = [
            Fine::STATUS_FROZEN, Fine::STATUS_APPEALED, Fine::STATUS_UPHELD,
            Fine::STATUS_COLLECTED, Fine::STATUS_OVERTURNED, Fine::STATUS_CANCELLED,
        ];

        return view('admin-v2.fines.index', compact('fines', 'status', 'statuses'));
    }

    public function create()
    {
        return view('admin-v2.fines.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'reason' => ['required', 'string', 'max:1000'],
            'appeal_days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);

        $fine = $this->fines->levy(
            userId: (int) $data['user_id'],
            amount: (float) $data['amount'],
            reason: $data['reason'],
            adminId: (int) $request->user()->id,
            appealDays: (int) ($data['appeal_days'] ?? FineService::DEFAULT_APPEAL_DAYS),
        );

        return redirect()
            ->route('admin.fines.show', $fine->id)
            ->with('status', __('فُرضت الغرامة وجُمّد ما أمكن من المبلغ.'));
    }

    public function show(int $fine)
    {
        $fine = Fine::query()
            ->with(['user:id,name,email,phone', 'appeals'])
            ->findOrFail($fine);

        return view('admin-v2.fines.show', compact('fine'));
    }

    public function decideAppeal(Request $request, int $fine)
    {
        $data = $request->validate([
            'decision' => ['required', 'in:accept,reject'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $model = Fine::findOrFail($fine);
        $this->fines->decideAppeal(
            $model,
            (int) $request->user()->id,
            $data['decision'] === 'accept',
            $data['note'] ?? null
        );

        return back()->with('status', __('سُجّل قرار الاعتراض.'));
    }

    public function cancel(Request $request, int $fine)
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        $model = Fine::findOrFail($fine);
        $this->fines->cancel($model, (int) $request->user()->id, $data['note'] ?? null);

        return back()->with('status', __('أُلغيت الغرامة وفُكّ التجميد.'));
    }
}
