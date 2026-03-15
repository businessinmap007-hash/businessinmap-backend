@extends('admin-v2.layouts.master')

@section('title', 'Create Price Rule')
@section('body_class', 'admin-v2-bookable-price-rule-create')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">إضافة قاعدة تسعير</h1>
            <div class="a2-page-subtitle">
                {{ $item->title }}
            </div>
        </div>

        <div class="a2-page-actions">
            <a class="a2-btn a2-btn-ghost" href="{{ route('admin.bookable-items.price-rules.index', $item) }}">
                رجوع
            </a>
        </div>
    </div>

    <div class="a2-card a2-page-narrow">
        @if ($errors->any())
            <div class="a2-alert a2-alert-danger a2-mb-12">
                <div class="a2-fw-900 a2-mb-8">يوجد أخطاء</div>
                <ul class="a2-errors-list">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.bookable-items.price-rules.store', $item) }}">
            @csrf

            <div class="a2-form-grid">
                <div class="a2-form-group a2-field-full">
                    <label class="a2-label">Title</label>
                    <input class="a2-input" name="title" value="{{ old('title') }}" placeholder="اسم القاعدة">
                </div>

                <div class="a2-form-group">
                    <label class="a2-label">Rule Type</label>
                    <select class="a2-select" name="rule_type" required>
                        <option value="date_range" @selected(old('rule_type') === 'date_range')>date_range</option>
                        <option value="special_day" @selected(old('rule_type') === 'special_day')>special_day</option>
                        <option value="season" @selected(old('rule_type') === 'season')>season</option>
                        <option value="weekday" @selected(old('rule_type') === 'weekday')>weekday</option>
                        <option value="default" @selected(old('rule_type') === 'default')>default</option>
                    </select>
                </div>

                <div class="a2-form-group">
                    <label class="a2-label">Price Type</label>
                    <select class="a2-select" name="price_type" required>
                        <option value="fixed" @selected(old('price_type') === 'fixed')>fixed</option>
                        <option value="delta" @selected(old('price_type') === 'delta')>delta</option>
                        <option value="percent" @selected(old('price_type') === 'percent')>percent</option>
                    </select>
                </div>
                <select class="a2-select" name="is_active"><option value="1" selected>Yes</option><option value="0">No</option></select>


                <div class="a2-form-group">
                    <label class="a2-label">Start Date</label>
                    <input class="a2-input" type="date" name="start_date" value="{{ old('start_date') }}" required>
                </div>

                <div class="a2-form-group">
                    <label class="a2-label">End Date</label>
                    <input class="a2-input" type="date" name="end_date" value="{{ old('end_date') }}" required>
                </div>

                <div class="a2-form-group">
                    <label class="a2-label">Price Value</label>
                    <input class="a2-input" type="number" step="0.01" name="price_value" value="{{ old('price_value') }}" required>
                </div>

                <div class="a2-form-group">
                    <label class="a2-label">Currency</label>
                    <input class="a2-input" name="currency" value="{{ old('currency', 'EGP') }}">
                </div>

                <div class="a2-form-group">
                    <label class="a2-label">Priority</label>
                    <input class="a2-input" type="number" name="priority" value="{{ old('priority', 100) }}">
                </div>

                <div class="a2-form-group a2-field-full">
                    <label class="a2-label">Notes</label>
                    <textarea class="a2-textarea" name="notes" rows="4">{{ old('notes') }}</textarea>
                </div>
            </div>

            <div class="a2-actionsbar a2-mt-16">
                <button class="a2-btn a2-btn-primary" type="submit">حفظ</button>
                <a class="a2-btn a2-btn-ghost" href="{{ route('admin.bookable-items.price-rules.index', $item) }}">إلغاء</a>
            </div>
        </form>
    </div>
</div>
@endsection
