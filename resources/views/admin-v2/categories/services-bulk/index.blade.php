@extends('admin-v2.layouts.master')

@section('title', 'Bulk Category Services')
@section('body_class', 'admin-v2-category-services-bulk')

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">{{ __('إدارة خدمات التصنيفات الفرعية') }}</h1>
        <div class="a2-page-subtitle">
            {{ __('اختر تصنيفًا رئيسيًا ثم حدّد مجموعة تصنيفات فرعية وطبّق عليها الخدمات دفعة واحدة') }}
        </div>
    </div>
</div>

<div class="a2-page-card">
    @if(session('success'))
        <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
    @endif

    <form method="GET" action="{{ route('admin.categories.services-bulk.index') }}" class="a2-card a2-card--section" style="margin-bottom:16px;">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">{{ __('اختيار التصنيف الرئيسي') }}</div>
                <div class="a2-card-sub">{{ __('سيتم عرض كل التصنيفات الفرعية التابعة له') }}</div>
            </div>
        </div>

        <div class="a2-form-grid">
            <div class="a2-form-group">
                <label class="a2-label">{{ __('التصنيف الرئيسي') }}</label>
                <select name="root_id" class="a2-select" onchange="this.form.submit()">
                    <option value="">{{ __('اختر التصنيف الرئيسي') }}</option>
                    @foreach($roots as $r)
                        <option value="{{ $r->id }}" @selected((int) $rootId === (int) $r->id)>
                            {{ $r->name_ar ?: ($r->name_en ?: ('#'.$r->id)) }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </form>

    <form method="POST" action="{{ route('admin.categories.services-bulk.apply') }}">
        @csrf

        <input type="hidden" name="root_id" value="{{ (int) $rootId }}">

        <div class="a2-card a2-card--section" style="margin-bottom:16px;">
            <div class="a2-card-head">
                <div>
                    <div class="a2-card-title">{{ __('التصنيفات الفرعية') }}</div>
                    <div class="a2-card-sub">{{ __('يمكنك اختيار أكثر من تصنيف فرعي') }}</div>
                </div>
            </div>

            @if($children->count())
                <div style="margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap;">
                    <button type="button" class="a2-btn a2-btn-ghost" onclick="toggleAll('.js-child-cat', true)">{{ __('تحديد الكل') }}</button>
                    <button type="button" class="a2-btn a2-btn-ghost" onclick="toggleAll('.js-child-cat', false)">{{ __('إلغاء الكل') }}</button>
                </div>

                <div class="a2-check-grid">
                    @foreach($children as $child)
                        <label class="a2-check-card">
                            <input type="checkbox" class="js-child-cat" name="category_ids[]" value="{{ $child->id }}">
                            <span>
                                <strong>{{ $child->name_ar ?: ($child->name_en ?: ('#'.$child->id)) }}</strong>
                                <small>#{{ $child->id }}</small>
                            </span>
                        </label>
                    @endforeach
                </div>
            @else
                <div class="a2-alert a2-alert-warning">{{ __('لا توجد تصنيفات فرعية لعرضها.') }}</div>
            @endif
        </div>

        <div class="a2-card a2-card--section" style="margin-bottom:16px;">
            <div class="a2-card-head">
                <div>
                    <div class="a2-card-title">{{ __('الخدمات') }}</div>
                    <div class="a2-card-sub">{{ __('اختر خدمة أو أكثر لتطبيقها على التصنيفات المحددة') }}</div>
                </div>
            </div>

            <div style="margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap;">
                <button type="button" class="a2-btn a2-btn-ghost" onclick="toggleAll('.js-service-box', true)">{{ __('تحديد الكل') }}</button>
                <button type="button" class="a2-btn a2-btn-ghost" onclick="toggleAll('.js-service-box', false)">{{ __('إلغاء الكل') }}</button>
            </div>

            <div class="a2-check-grid">
                @foreach($platformServices as $service)
                    <label class="a2-check-card">
                        <input type="checkbox" class="js-service-box" name="platform_service_ids[]" value="{{ $service->id }}">
                        <span>
                            <strong>{{ $service->name_ar ?: ($service->name_en ?: $service->key) }}</strong>
                            <small dir="ltr">{{ $service->key }}</small>
                        </span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="a2-card a2-card--section">
            <div class="a2-card-head">
                <div>
                    <div class="a2-card-title">{{ __('نوع العملية') }}</div>
                    <div class="a2-card-sub">{{ __('حدد طريقة التطبيق على التصنيفات المختارة') }}</div>
                </div>
            </div>

            <div class="a2-check-grid a2-check-grid--sm">
                <label class="a2-check-card">
                    <input type="radio" name="mode" value="append" checked>
                    <span>
                        <strong>Append</strong>
                        <small>{{ __('إضافة الخدمات الجديدة بدون حذف القديمة') }}</small>
                    </span>
                </label>

                <label class="a2-check-card">
                    <input type="radio" name="mode" value="replace">
                    <span>
                        <strong>Replace</strong>
                        <small>{{ __('استبدال كل الخدمات الحالية بالخدمات المحددة') }}</small>
                    </span>
                </label>

                <label class="a2-check-card">
                    <input type="radio" name="mode" value="remove">
                    <span>
                        <strong>Remove</strong>
                        <small>{{ __('حذف الخدمات المحددة فقط من التصنيفات المختارة') }}</small>
                    </span>
                </label>
            </div>
        </div>

        <div class="a2-page-actions" style="justify-content:flex-end;margin-top:16px;">
            <button type="submit" class="a2-btn a2-btn-primary">
                {{ __('تنفيذ العملية') }}
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
function toggleAll(selector, checked) {
    document.querySelectorAll(selector).forEach(function (el) {
        el.checked = checked;
    });
}
</script>
@endpush
@endsection