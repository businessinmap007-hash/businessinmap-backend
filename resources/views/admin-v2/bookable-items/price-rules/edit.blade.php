@extends('admin-v2.layouts.master')

@section('title', 'Edit Price Rule')
@section('body_class', 'admin-v2-bookable-price-rule-edit')

@section('content')
@include('admin-v2.bookable-items.partials.tabs', ['item' => $item])

<div class="a2-page a2-page-narrow">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">تعديل قاعدة تسعير</h1>
            <div class="a2-page-subtitle">
                {{ $item->title }} — تعديل قاعدة السعر
            </div>
        </div>

        <div class="a2-page-actions">
            <a class="a2-btn a2-btn-ghost" href="{{ route('admin.bookable-items.price-rules.index', $item) }}">
                رجوع
            </a>
        </div>
    </div>

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

    <div class="a2-card a2-card--section">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">ملخص العنصر</div>
                <div class="a2-card-sub">العنصر الذي تطبق عليه القاعدة</div>
            </div>
        </div>

        <div class="a2-form-grid">
            <div><strong>Title:</strong> {{ $item->title ?: '—' }}</div>
            <div><strong>Type:</strong> <span dir="ltr">{{ $item->item_type ?: '—' }}</span></div>
            <div><strong>Code:</strong> <span dir="ltr">{{ $item->code ?: '—' }}</span></div>
            <div><strong>Base Price:</strong> {{ number_format((float) ($item->price ?? 0), 2) }}</div>
        </div>
    </div>

    <div class="a2-card a2-card--section" style="margin-top:16px;">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">بيانات قاعدة التسعير</div>
                <div class="a2-card-sub">تعديل نوع القاعدة والنطاق الزمني والتأثير السعري</div>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.bookable-items.price-rules.update', [$item, $rule]) }}">
            @csrf
            @method('PUT')

            <div class="a2-form-grid">
                <div class="a2-form-group a2-field-full">
                    <label class="a2-label">Title</label>
                    <input class="a2-input" name="title" value="{{ old('title', $rule->title) }}" placeholder="اسم القاعدة">
                    @error('title')
                        <div class="a2-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="a2-form-group">
                    <label class="a2-label">Rule Type <span class="a2-danger">*</span></label>
                    <select class="a2-select" name="rule_type" required>
                        <option value="date_range" @selected(old('rule_type', $rule->rule_type) === 'date_range')>date_range</option>
                        <option value="special_day" @selected(old('rule_type', $rule->rule_type) === 'special_day')>special_day</option>
                        <option value="season" @selected(old('rule_type', $rule->rule_type) === 'season')>season</option>
                        <option value="weekday" @selected(old('rule_type', $rule->rule_type) === 'weekday')>weekday</option>
                        <option value="default" @selected(old('rule_type', $rule->rule_type) === 'default')>default</option>
                    </select>
                    @error('rule_type')
                        <div class="a2-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="a2-form-group">
                    <label class="a2-label">Price Type <span class="a2-danger">*</span></label>
                    <select class="a2-select" name="price_type" required>
                        <option value="fixed" @selected(old('price_type', $rule->price_type) === 'fixed')>fixed</option>
                        <option value="delta" @selected(old('price_type', $rule->price_type) === 'delta')>delta</option>
                        <option value="percent" @selected(old('price_type', $rule->price_type) === 'percent')>percent</option>
                    </select>
                    @error('price_type')
                        <div class="a2-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="a2-form-group">
                    <label class="a2-label">Active</label>
                    <select class="a2-select" name="is_active">
                        <option value="1" @selected((string) old('is_active', (int) $rule->is_active) === '1')>Yes</option>
                        <option value="0" @selected((string) old('is_active', (int) $rule->is_active) === '0')>No</option>
                    </select>
                    @error('is_active')
                        <div class="a2-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            @include('admin-v2.partials.forms.date-range', [
                'startName' => 'start_date',
                'endName' => 'end_date',
                'startLabel' => 'Start Date',
                'endLabel' => 'End Date',
                'startValue' => old('start_date', optional($rule->start_date)->format('Y-m-d')),
                'endValue' => old('end_date', optional($rule->end_date)->format('Y-m-d')),
                'required' => true,
                'hint' => 'عدل النطاق الزمني الذي ستطبق عليه قاعدة التسعير.'
            ])

            <div class="a2-form-grid" style="margin-top:16px;">
                <div class="a2-form-group">
                    <label class="a2-label">Price Value <span class="a2-danger">*</span></label>
                    <input class="a2-input" type="number" step="0.01" name="price_value" value="{{ old('price_value', $rule->price_value) }}" required>
                    @error('price_value')
                        <div class="a2-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="a2-form-group">
                    <label class="a2-label">Currency</label>
                    <input class="a2-input" name="currency" value="{{ old('currency', $rule->currency ?: 'EGP') }}">
                    @error('currency')
                        <div class="a2-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="a2-form-group">
                    <label class="a2-label">Priority</label>
                    <input class="a2-input" type="number" name="priority" value="{{ old('priority', $rule->priority ?? 100) }}">
                    @error('priority')
                        <div class="a2-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="a2-form-group a2-field-full">
                    <label class="a2-label">Notes</label>
                    <textarea class="a2-textarea" name="notes" rows="4">{{ old('notes', $rule->notes) }}</textarea>
                    @error('notes')
                        <div class="a2-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="a2-page-actions" style="justify-content:flex-end;margin-top:16px;">
                <a class="a2-btn a2-btn-ghost" href="{{ route('admin.bookable-items.price-rules.index', $item) }}">
                    إلغاء
                </a>
                <button class="a2-btn a2-btn-primary" type="submit">تحديث</button>
            </div>
        </form>
    </div>
</div>
@endsection