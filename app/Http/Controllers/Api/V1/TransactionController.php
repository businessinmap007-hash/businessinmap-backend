<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use App\Libraries\Main;
use App\Models\Transaction;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    protected $main;

    public function __construct(Main $main)
    {
        $this->main = $main;
    }

    /**
     * ✅ عرض جميع المعاملات الخاصة بالمستخدم
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Transaction::where('user_id', $user->id)
            ->orderByDesc('created_at');

        if ($request->filled('type')) {
            $query->where('status', $request->type);
        }

        if ($request->filled('operation')) {
            $query->where('operation', $request->operation);
        }

        $transactions = $query->paginate(15);

        return TransactionResource::collection($transactions)
            ->additional(['status' => 200, 'message' => 'Transactions fetched successfully']);
    }

    /**
     * ✅ عرض عملية واحدة بالتفصيل
     */
    public function show(Request $request, $id)
    {
        $transaction = Transaction::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$transaction) {
            return response()->json([
                'status' => 404,
                'message' => 'Transaction not found',
            ]);
        }

        return (new TransactionResource($transaction))
            ->additional(['status' => 200, 'message' => 'Transaction details']);
    }

    /**
     * ✅ عرض الرصيد الإجمالي للمستخدم
     */
    public function balance(Request $request)
    {
        $user = $request->user();
        $balance = $this->main->calculateUserBalance($user);

        return response()->json([
            'status' => 200,
            'message' => 'User balance fetched successfully',
            'balance' => number_format($balance, 2, '.', ''),
        ]);
    }

    /**
     * ✅ عرض ملخص سريع (إجمالي الإيداعات / السحوبات)
     */
    public function summary(Request $request)
    {
        $user = $request->user();

        $deposits = Transaction::where('user_id', $user->id)
            ->where('status', 'deposit')
            ->sum('price');

        $withdrawals = Transaction::where('user_id', $user->id)
            ->where('status', 'withdrawal')
            ->sum('price');

        return response()->json([
            'status' => 200,
            'message' => 'Summary fetched successfully',
            'summary' => [
                'deposits' => number_format($deposits, 2, '.', ''),
                'withdrawals' => number_format($withdrawals, 2, '.', ''),
                'net_balance' => number_format($deposits - $withdrawals, 2, '.', ''),
            ]
        ]);
    }
}
