<?php

namespace App\Http\Controllers\Admin;

use App\Models\Album;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class TransactionController extends Controller
{
    public function __invoke(Request $request)
    {
        $query = Transaction::orderBy('created_at', 'desc');

        /**
         * @@ check if businessId exist to get only sponsors
         *@@ else get all.
         */
        if (isset($request->businessId) && $request->businessId != "")
            $query->whereUserId($request->businessId);


        $transactions = $query->get();

        // Return View Sponsors List.
        return view('admin.transactions.index', compact('transactions'));
    }


    public function store(Request $request, User $user)
    {

        $dataOwner = array(
            'status' => 'deposit',
            'price' => sprintf("%.2f", $request->price),
            'operation' => 'recharge',
            'notes' => 'شحن الحساب بواسطة إدارة التطبيق',
            'target_id' => null
        );
        if ($user->transactions()->create($dataOwner))

            return returnedResponse(200, 'لقد تم شحن الحساب بنجاح', null, route('transactions.index').'?businessId='.$user->id);

    }
}
