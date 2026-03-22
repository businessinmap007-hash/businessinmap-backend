@extends('admin-v2.layouts.master')

@section('title', 'Bulk Child Options')
@section('body_class', 'admin-v2 admin-v2-category-child-options-bulk')

@section('content')
@php
    $parentIdInt = (int) ($parentId ?? 0);
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">Bulk Options للأقسام الفرعية</h1>
            <div class="a2-page-subtitle">
                تطبيق إضافة أو استبدال أو إزالة Options على مجموعة أقسام فرعية مرة واحدة
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">
            {{ $errors->first() }}
        </div>
    @endif

    @if($parent)
        <div class="a2-card a2-card--section" style="margin-bottom:16px;">
            <div class="a2-card-head">
                <div>
                    <div class="a2-card-title">القسم الرئيسي المختار</div>
                    <div class="a2-card-sub">
                        {{ $parent->name_ar ?: ($parent->name_en ?: '—') }}
                        <span class="a2-muted">#{{ $parent->id }}</span>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.category-child-options.bulk.update') }}">
        @csrf

        <input type="hidden" name="parent_id" value="{{ $parentIdInt }}">

        <div class="a2-card a2-card--section" style="margin-bottom:16px;">
            <div class="a2-card-head">
                <div>
                    <div class="a2-card-title">الأقسام الفرعية المحددة</div>
                    <div class="a2-card-sub">سيتم تطبيق العملية التالية على هذه الأقسام فقط</div>
                </div>
            </div>

            <div class="a2-option-chip-grid">
                @foreach($children as $child)
                    <input type="hidden" name="child_ids[]" value="{{ $child->id }}">

                    <div class="a2-option-chip-card">
                        <div class="a2-option-chip-title">
                            {{ $child->name_ar ?: ($child->name_en ?: ('#' . $child->id)) }}
                        </div>
                        <div class="a2-option-chip-sub" dir="ltr">
                            #{{ $child->id }}
                            @if(!empty($child->name_en))
                                — {{ $child->name_en }}
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="a2-card a2-card--section" style="margin-bottom:16px;">
            <div class="a2-card-head">
                <div>
                    <div class="a2-card-title">نوع العملية</div>
                    <div class="a2-card-sub">حدد طريقة التعامل مع Options الحالية</div>
                </div>
            </div>

            <div class="a2-form-grid-3">
                <label class="a2-check-card">
                    <input type="radio" name="mode" value="append" checked>
                    <span>
                        <strong>Append</strong>
                        <small style="display:block;color:var(--a2-text-soft);margin-top:4px;">
                            إضافة الخيارات الجديدة مع الإبقاء على الحالية
                        </small>
                    </span>
                </label>

                <label class="a2-check-card">
                    <input type="radio" name="mode" value="replace">
                    <span>
                        <strong>Replace</strong>
                        <small style="display:block;color:var(--a2-text-soft);margin-top:4px;">
                            استبدال كل الخيارات الحالية بالمحددة الآن
                        </small>
                    </span>
                </label>

                <label class="a2-check-card">
                    <input type="radio" name="mode" value="remove">
                    <span>
                        <strong>Remove</strong>
                        <small style="display:block;color:var(--a2-text-soft);margin-top:4px;">
                            إزالة الخيارات المحددة فقط من الأطفال المختارين
                        </small>
                    </span>
                </label>
            </div>
        </div>

        <div class="a2-card a2-card--section">
            <div class="a2-card-head">
                <div>
                    <div class="a2-card-title">الخيارات</div>
                    <div class="a2-card-sub">حدد Option واحدة أو أكثر</div>
                </div>
            </div>

            <div class="a2-card" style="padding:12px;margin-bottom:12px;">
                <input type="text"
                       id="bulkOptionsSearch"
                       class="a2-input"
                       placeholder="بحث في الخيارات...">
            </div>

            <div id="bulkOptionsList" class="a2-check-grid" style="max-height:520px;overflow:auto;">
                @forelse($options as $option)
                    <label class="a2-check-card option-card" data-label="{{ strtolower(($option->name_ar ?? '') . ' ' . ($option->name_en ?? '')) }}">
                        <input type="checkbox" name="option_ids[]" value="{{ $option->id }}">
                        <span>
                            <strong>{{ $option->name_ar ?: ($option->name_en ?: ('#' . $option->id)) }}</strong>
                            <small style="display:block;color:var(--a2-text-soft);margin-top:4px;" dir="ltr">
                                #{{ $option->id }}
                                @if(!empty($option->name_en))
                                    — {{ $option->name_en }}
                                @endif
                            </small>
                        </span>
                    </label>
                @empty
                    <div class="a2-alert a2-alert-warning">
                        لا توجد Options متاحة.
                    </div>
                @endforelse
            </div>
        </div>

        <div class="a2-page-actions" style="justify-content:flex-end;margin-top:16px;">
            <a href="{{ route('admin.categories.index', $parentIdInt > 0 ? ['root_id' => $parentIdInt] : []) }}"
               class="a2-btn a2-btn-ghost">
                رجوع
            </a>

            <button type="submit" class="a2-btn a2-btn-primary">
                تطبيق العملية
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('bulkOptionsSearch');
    const list = document.getElementById('bulkOptionsList');

    if (!input || !list) return;

    input.addEventListener('input', function () {
        const q = (input.value || '').toLowerCase().trim();

        list.querySelectorAll('.option-card').forEach(function (card) {
            const label = (card.getAttribute('data-label') || '').toLowerCase();
            card.style.display = (!q || label.includes(q)) ? '' : 'none';
        });
    });
});
</script>
@endpush
@endsection