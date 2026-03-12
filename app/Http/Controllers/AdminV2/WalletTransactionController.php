<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class WalletTransactionController extends Controller
{
    private const PER_PAGE_ALLOWED = [10, 20, 50, 100];

    private function normalizePerPage($perPage): int
    {
        $perPage = (int)$perPage;
        return in_array($perPage, self::PER_PAGE_ALLOWED, true) ? $perPage : 50;
    }

    private function keepQs(Request $request): array
    {
        return $request->only(['q','filter','notes','per_page','sort','dir']);
    }

    

    private function applyFilter($query, string $filter): void
    {
        if ($filter !== '') {
            if (str_starts_with($filter, 'st:')) {
                $query->where('status', substr($filter, 3));
            } elseif (str_starts_with($filter, 'dir:')) {
                $query->where('direction', substr($filter, 4));
            } elseif (str_starts_with($filter, 'tp:')) {
                $query->where('type', substr($filter, 3));
            } elseif ($filter === 'combo:deposit_in') {
                $query->where('type', 'deposit')->where('direction', 'in');
            } elseif ($filter === 'combo:withdraw_out') {
                $query->where('type', 'withdraw')->where('direction', 'out');
            }
        }
    }

    private function totalsForQuery($baseQuery): array
    {
        // إجماليات على كل النتائج بعد الفلترة (بدون pagination)
        $row = (clone $baseQuery)
            ->selectRaw("
                COUNT(*) as cnt,
                COALESCE(SUM(CASE WHEN direction='in'  THEN amount ELSE 0 END),0) as sum_in,
                COALESCE(SUM(CASE WHEN direction='out' THEN amount ELSE 0 END),0) as sum_out
            ")
            ->first();

        $sumIn  = (float)($row->sum_in ?? 0);
        $sumOut = (float)($row->sum_out ?? 0);

        return [
            'count'   => (int)($row->cnt ?? 0),
            'sum_in'  => $sumIn,
            'sum_out' => $sumOut,
            'net'     => $sumIn - $sumOut,
        ];
    }

 public function index(Request $request)
    {
        $q       = trim((string)$request->get('q', ''));
        $filter  = trim((string)$request->get('filter', ''));
        $noteId  = (int)$request->get('note_id', 0); // ✅ بدل note النص
        $perPage = $this->normalizePerPage($request->get('per_page', 50));

        $sortAllowed = ['id','wallet_id','type','direction','amount','status','created_at'];
        $sort = (string)$request->get('sort', 'id');
        if (!in_array($sort, $sortAllowed, true)) $sort = 'id';

        $dir = strtolower((string)$request->get('dir', 'desc'));
        $dir = in_array($dir, ['asc','desc'], true) ? $dir : 'desc';

        $base = WalletTransaction::query()
            ->with([
                'user:id,name',
                'noteTemplate:id,title', // ✅
            ]);

        if ($q !== '') {
            $base->where(function ($w) use ($q) {
                $w->where('id', $q)
                ->orWhere('wallet_id', $q)
                ->orWhere('user_id', $q)
                ->orWhere('type', 'like', "%{$q}%");
            })
            ->orWhereHas('user', function($u) use ($q){
                $u->where('name', 'like', "%{$q}%");
            })
            ->orWhereHas('noteTemplate', function($n) use ($q){
                $n->where('title', 'like', "%{$q}%");
            });
        }

        $this->applyFilter($base, $filter);

        // ✅ options من جدول templates وليس من wallet_transactions.note
        $notesOptions = \App\Models\WalletNoteTemplate::query()
            ->where('is_active', true)
            ->orderBy('sort')
            ->orderBy('id', 'desc')
            ->limit(500)
            ->get(['id','title']);

        if ($noteId > 0) {
            $base->where('note_id', $noteId);
        }

        $items = (clone $base)
            ->orderBy($sort, $dir)
            ->paginate($perPage)
            ->withQueryString();

        return view('admin-v2.wallet-transactions.index', [
            'items' => $items,
            'q' => $q,
            'filter' => $filter,
            'noteId' => $noteId,                 // ✅
            'notesOptions' => $notesOptions,      // ✅ collection(id,title)
            'perPage' => $perPage,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

        /**
         * صفحة معاملات مستخدم (محاسبية) - تعرض كل التعاملات + totals + balance/locked
         */
    public function user(Request $request, User $user)
    {
        $q       = trim((string)$request->get('q', ''));
        $filter  = trim((string)$request->get('filter', ''));
        $noteId  = (int)$request->get('note_id', 0); // ✅ بدل notes النص
        $perPage = $this->normalizePerPage($request->get('per_page', 10));

        $sortAllowed = ['id','type','amount','status','created_at'];
        $sort = (string)$request->get('sort', 'id');
        if (!in_array($sort, $sortAllowed, true)) $sort = 'id';

        $dir = strtolower((string)$request->get('dir', 'desc'));
        $dir = in_array($dir, ['asc','desc'], true) ? $dir : 'desc';

        $base = WalletTransaction::query()
            ->where('user_id', $user->id)
            ->with(['noteTemplate:id,title']); // ✅

        if ($q !== '') {
            $base->where(function ($w) use ($q) {
                $w->where('id', $q)
                ->orWhere('wallet_id', $q)
                ->orWhere('type', 'like', "%{$q}%");
            })
            ->orWhereHas('noteTemplate', function($n) use ($q){
                $n->where('title', 'like', "%{$q}%");
            });
        }

        $this->applyFilter($base, $filter);

        // ✅ dropdown options
        $notesOptions = \App\Models\WalletNoteTemplate::query()
            ->where('is_active', true)
            ->orderBy('sort')
            ->orderBy('id', 'desc')
            ->limit(500)
            ->get(['id','title']);

        if ($noteId > 0) {
            $base->where('note_id', $noteId);
        }

        $row = (clone $base)
            ->selectRaw("
                COUNT(*) as cnt,
                COALESCE(SUM(CASE WHEN direction='in'  THEN amount ELSE 0 END),0) as sum_in,
                COALESCE(SUM(CASE WHEN direction='out' THEN amount ELSE 0 END),0) as sum_out
            ")
            ->first();

        $sumIn  = (float)($row->sum_in ?? 0);
        $sumOut = (float)($row->sum_out ?? 0);

        $totals = [
            'count'   => (int)($row->cnt ?? 0),
            'sum_in'  => $sumIn,
            'sum_out' => $sumOut,
            'net'     => $sumIn - $sumOut,
        ];

        $items = (clone $base)
            ->orderBy($sort, $dir)
            ->paginate($perPage)
            ->withQueryString();

        $wallet = Wallet::query()->where('user_id', $user->id)->first();

        $balance = (float)($wallet->balance ?? 0);
        $locked  = (float)($wallet->locked_balance ?? 0);

        $walletTotalIn  = (float)($wallet->total_in ?? 0);
        $walletTotalOut = (float)($wallet->total_out ?? 0);

        return view('admin-v2.wallet-transactions.user', compact(
            'user','wallet','balance','locked','walletTotalIn','walletTotalOut',
            'items','q','filter','noteId','notesOptions','perPage','sort','dir','totals'
        ));
    }


    public function show(Request $request, \App\Models\WalletTransaction $walletTransaction)
    {
        $tx = $walletTransaction->load('user');

        return view('admin-v2.wallet-transactions.show', compact('tx'));
    }
    

//     public function show(User $user, WalletTransaction $walletTransaction)
//     {
//         abort_unless((int)$walletTransaction->user_id === (int)$user->id, 404);

//         $wallet = Wallet::query()->where('user_id', $user->id)->first();
//         $notesOptions = (clone $baseQuery)
//         ->whereNotNull('note')
//         ->where('note', '<>', '')
//         ->select('note')
//         ->distinct()
//         ->orderBy('note')
//         ->limit(200) // مهم للأداء
//         ->pluck('note')
//         ->values()
//         ->all();

//        return view('admin-v2.wallet-transactions.user', compact(
//     'user', 'items', 'totals', 'balance', 'locked',
//     'q', 'filter', 'notes', 'perPage', 'sort', 'dir',
//     'notesOptions'
// ));
//     }
}