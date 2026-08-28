@extends('business.layouts.master')

@section('title', __('شركاء موثوقون'))

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">{{ __('شركاء موثوقون') }}</h1>
        <div class="a2-page-subtitle">{{ __('عميل تعاملت معه من قبل وتثق فيه؟ وثّقه هنا فيُعفى من شرط الديبوزت في طلباته منك — طالما احتفظ بمستوى ضمانه.') }}</div>
    </div>
</div>

@if(session('success'))
    <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="a2-alert a2-alert-danger">
        @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
    </div>
@endif

<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">{{ __('توثيق شريك') }}</div>
            <div class="a2-card-sub">{{ __('برقم هاتفه — يجب أن يكون له حساب مسجَّل ولديه ضمان فعّال بالفعل.') }}</div>
        </div>
    </div>
    <form method="POST" action="{{ route('business.trusted-partners.store') }}">
        @csrf
        <div class="a2-form-grid" style="grid-template-columns:1fr auto;align-items:end;gap:12px;">
            <div class="a2-form-group">
                <label class="a2-label" for="phone">{{ __('رقم هاتف الشريك') }} <span class="a2-danger">*</span></label>
                <input class="a2-input" id="phone" name="phone" value="{{ old('phone') }}" dir="ltr" placeholder="01xxxxxxxxx" required>
            </div>
            <div class="a2-form-group">
                <button type="submit" class="a2-btn a2-btn-primary">{{ __('توثيق') }}</button>
            </div>
        </div>
    </form>
</div>

<div class="a2-card">
    <div class="a2-table-wrap">
        <table class="a2-table">
            <thead>
                <tr>
                    <th>{{ __('الشريك') }}</th>
                    <th>{{ __('الحالة') }}</th>
                    <th class="a2-text-right">{{ __('إجراءات') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($partners as $partner)
                    <tr>
                        <td>
                            <div class="a2-fw-900">{{ optional($partner->user)->name ?? __('بدون اسم') }}</div>
                            <div class="a2-muted" dir="ltr">{{ optional($partner->user)->phone }}</div>
                        </td>
                        <td>
                            @if($partner->is_active)
                                <span class="a2-pill a2-pill-success">{{ __('موثّق') }}</span>
                            @else
                                <span class="a2-pill a2-pill-gray">{{ __('ملغى') }}</span>
                            @endif
                        </td>
                        <td class="a2-text-right">
                            <form method="POST" action="{{ route('business.trusted-partners.update', $partner->id) }}">
                                @csrf
                                @method('PUT')
                                @if($partner->is_active)
                                    <input type="hidden" name="is_active" value="0">
                                    <button class="a2-btn a2-btn-sm a2-btn-ghost" type="submit">{{ __('إلغاء التوثيق') }}</button>
                                @else
                                    <input type="hidden" name="is_active" value="1">
                                    <button class="a2-btn a2-btn-sm a2-btn-primary" type="submit">{{ __('إعادة التوثيق') }}</button>
                                @endif
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="a2-empty">{{ __('لا يوجد شركاء موثّقون بعد.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
