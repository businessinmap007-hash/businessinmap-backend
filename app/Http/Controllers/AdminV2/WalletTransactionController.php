<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletNoteTemplate;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class WalletTransactionController extends Controller
{
    private const PER_PAGE_ALLOWED = [10, 20, 50, 100];

    private function normalizePerPage($perPage): int
    {
        $perPage = (int) $perPage;

        return in_array($perPage, self::PER_PAGE_ALLOWED, true)
            ? $perPage
            : 50;
    }

    private function keepQs(Request $request): array
    {
        return $request->only([
            'q',
            'filter',
            'note_id',
            'payer',
            'fee_code',
            'reference_type',
            'reference_id',
            'booking_id',
            'per_page',
            'sort',
            'dir',
        ]);
    }

    private function applyFilter(Builder $query, string $filter): void
    {
        if ($filter === '') {
            return;
        }

        if (str_starts_with($filter, 'st:')) {
            $query->where('status', substr($filter, 3));
            return;
        }

        if (str_starts_with($filter, 'dir:')) {
            $query->where('direction', substr($filter, 4));
            return;
        }

        if (str_starts_with($filter, 'tp:')) {
            $query->where('type', substr($filter, 3));
            return;
        }

        if ($filter === 'combo:deposit_in') {
            $query->where('type', WalletTransaction::TYPE_DEPOSIT)
                ->where('direction', WalletTransaction::DIRECTION_IN);
            return;
        }

        if ($filter === 'combo:withdraw_out') {
            $query->where('type', WalletTransaction::TYPE_WITHDRAW)
                ->where('direction', WalletTransaction::DIRECTION_OUT);
            return;
        }

        if ($filter === 'combo:booking_fees') {
            $query->bookingFees();
            return;
        }

        if ($filter === 'combo:platform_fee_out') {
            $query->platformFees()
                ->where('direction', WalletTransaction::DIRECTION_OUT);
        }
    }

    private function totalsForQuery(Builder $baseQuery): array
    {
        $row = (clone $baseQuery)
            ->selectRaw("
                COUNT(*) as cnt,
                COALESCE(SUM(CASE WHEN direction='in'  THEN amount ELSE 0 END),0) as sum_in,
                COALESCE(SUM(CASE WHEN direction='out' THEN amount ELSE 0 END),0) as sum_out,
                COALESCE(SUM(CASE WHEN type='platform_fee' THEN amount ELSE 0 END),0) as platform_fees
            ")
            ->first();

        $sumIn = (float) ($row->sum_in ?? 0);
        $sumOut = (float) ($row->sum_out ?? 0);

        return [
            'count' => (int) ($row->cnt ?? 0),
            'sum_in' => $sumIn,
            'sum_out' => $sumOut,
            'net' => $sumIn - $sumOut,
            'platform_fees' => (float) ($row->platform_fees ?? 0),
        ];
    }

    private function applySearch(Builder $query, string $q): void
    {
        if ($q === '') {
            return;
        }

        $query->where(function ($w) use ($q) {
            $w->where('id', $q)
                ->orWhere('wallet_id', $q)
                ->orWhere('user_id', $q)
                ->orWhere('reference_id', $q)
                ->orWhere('idempotency_key', 'like', "%{$q}%")
                ->orWhere('type', 'like', "%{$q}%")
                ->orWhere('note', 'like', "%{$q}%")
                ->orWhere('meta->fee_code', 'like', "%{$q}%")
                ->orWhere('meta->payer', 'like', "%{$q}%")
                ->orWhere('meta->context', 'like', "%{$q}%")
                ->orWhereHas('user', function ($u) use ($q) {
                    $u->where('name', 'like', "%{$q}%");
                })
                ->orWhereHas('noteTemplate', function ($n) use ($q) {
                    $n->where('title', 'like', "%{$q}%");
                });
        });
    }

    private function applyExtraFilters(Builder $query, Request $request): void
    {
        $noteId = (int) $request->get('note_id', 0);
        $payer = trim((string) $request->get('payer', ''));
        $feeCode = trim((string) $request->get('fee_code', ''));
        $referenceType = trim((string) $request->get('reference_type', ''));
        $referenceId = trim((string) $request->get('reference_id', ''));
        $bookingId = (int) $request->get('booking_id', 0);

        if ($noteId > 0) {
            $query->where('note_id', $noteId);
        }

        if ($payer !== '') {
            $query->forPayer($payer);
        }

        if ($feeCode !== '') {
            $query->forFeeCode($feeCode);
        }

        if ($bookingId > 0) {
            $query->forBooking($bookingId);
        } elseif ($referenceType !== '') {
            $query->forReference($referenceType, $referenceId !== '' ? $referenceId : null);
        }
    }

    private function notesOptions()
    {
        return WalletNoteTemplate::query()
            ->where('is_active', true)
            ->orderBy('sort')
            ->orderByDesc('id')
            ->limit(500)
            ->get(['id', 'title']);
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $filter = trim((string) $request->get('filter', ''));
        $noteId = (int) $request->get('note_id', 0);

        $payer = trim((string) $request->get('payer', ''));
        $feeCode = trim((string) $request->get('fee_code', ''));
        $referenceType = trim((string) $request->get('reference_type', ''));
        $referenceId = trim((string) $request->get('reference_id', ''));
        $bookingId = (int) $request->get('booking_id', 0);

        $perPage = $this->normalizePerPage($request->get('per_page', 50));

        $sortAllowed = ['id', 'wallet_id', 'type', 'direction', 'amount', 'status', 'created_at'];
        $sort = (string) $request->get('sort', 'id');
        if (! in_array($sort, $sortAllowed, true)) {
            $sort = 'id';
        }

        $dir = strtolower((string) $request->get('dir', 'desc'));
        $dir = in_array($dir, ['asc', 'desc'], true) ? $dir : 'desc';

        $base = WalletTransaction::query()
            ->with([
                'user:id,name',
                'wallet:id,user_id,balance,locked_balance,status',
                'noteTemplate:id,title',
            ]);

        $this->applySearch($base, $q);
        $this->applyFilter($base, $filter);
        $this->applyExtraFilters($base, $request);

        $totals = $this->totalsForQuery($base);

        $items = (clone $base)
            ->orderBy($sort, $dir)
            ->paginate($perPage)
            ->withQueryString();

        $notesOptions = $this->notesOptions();

        return view('admin-v2.wallet-transactions.index', [
            'items' => $items,

            'q' => $q,
            'filter' => $filter,
            'noteId' => $noteId,
            'payer' => $payer,
            'feeCode' => $feeCode,
            'referenceType' => $referenceType,
            'referenceId' => $referenceId,
            'bookingId' => $bookingId,

            'notesOptions' => $notesOptions,
            'perPage' => $perPage,
            'sort' => $sort,
            'dir' => $dir,
            'totals' => $totals,

            'keepQs' => $this->keepQs($request),
        ]);
    }

    /**
     * صفحة معاملات مستخدم محدد.
     */
    public function user(Request $request, User $user)
    {
        $q = trim((string) $request->get('q', ''));
        $filter = trim((string) $request->get('filter', ''));
        $noteId = (int) $request->get('note_id', 0);

        $payer = trim((string) $request->get('payer', ''));
        $feeCode = trim((string) $request->get('fee_code', ''));
        $referenceType = trim((string) $request->get('reference_type', ''));
        $referenceId = trim((string) $request->get('reference_id', ''));
        $bookingId = (int) $request->get('booking_id', 0);

        $perPage = $this->normalizePerPage($request->get('per_page', 10));

        $sortAllowed = ['id', 'type', 'amount', 'status', 'created_at'];
        $sort = (string) $request->get('sort', 'id');
        if (! in_array($sort, $sortAllowed, true)) {
            $sort = 'id';
        }

        $dir = strtolower((string) $request->get('dir', 'desc'));
        $dir = in_array($dir, ['asc', 'desc'], true) ? $dir : 'desc';

        $base = WalletTransaction::query()
            ->where('user_id', $user->id)
            ->with([
                'wallet:id,user_id,balance,locked_balance,status',
                'noteTemplate:id,title',
            ]);

        $this->applySearch($base, $q);
        $this->applyFilter($base, $filter);
        $this->applyExtraFilters($base, $request);

        $totals = $this->totalsForQuery($base);

        $items = (clone $base)
            ->orderBy($sort, $dir)
            ->paginate($perPage)
            ->withQueryString();

        $wallet = Wallet::query()
            ->where('user_id', $user->id)
            ->first();

        $balance = (float) ($wallet->balance ?? 0);
        $locked = (float) ($wallet->locked_balance ?? 0);

        $walletTotalIn = (float) ($wallet->total_in ?? 0);
        $walletTotalOut = (float) ($wallet->total_out ?? 0);

        $notesOptions = $this->notesOptions();

        return view('admin-v2.wallet-transactions.user', compact(
            'user',
            'wallet',
            'balance',
            'locked',
            'walletTotalIn',
            'walletTotalOut',
            'items',
            'q',
            'filter',
            'noteId',
            'payer',
            'feeCode',
            'referenceType',
            'referenceId',
            'bookingId',
            'notesOptions',
            'perPage',
            'sort',
            'dir',
            'totals'
        ));
    }

    public function show(Request $request, WalletTransaction $walletTransaction)
    {
        $tx = $walletTransaction->load([
            'user:id,name',
            'wallet:id,user_id,balance,locked_balance,status',
            'noteTemplate:id,title',
        ]);

        return view('admin-v2.wallet-transactions.show', compact('tx'));
    }
}