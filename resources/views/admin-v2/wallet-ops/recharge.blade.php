@extends('admin-v2.layouts.master')

@section('title', 'Wallet Recharge')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">شحن المحفظة</h1>
        </div>
    </div>

    <div class="a2-card">
        <form method="POST" action="{{ route('admin.wallet-ops.recharge') }}">
            @csrf

            <input type="hidden" name="user_id" value="{{ $user?->id }}">

            <div class="a2-form-grid">
                <div>
                    <label class="a2-label">المستخدم</label>
                    <input class="a2-input" value="{{ $user?->name }}" disabled>
                </div>

                <div>
                    <label class="a2-label">المبلغ</label>
                    <input class="a2-input" name="amount" type="number" min="1" step="0.01" required>
                </div>

                <div>
                    <label class="a2-label">ملاحظة</label>
                    <textarea class="a2-input" name="note"></textarea>
                </div>
            </div>

            <button class="a2-btn a2-btn-primary" type="submit">شحن</button>
        </form>
    </div>
</div>
@endsection