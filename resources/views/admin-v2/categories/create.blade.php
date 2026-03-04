@extends('admin-v2.layouts.master')

@section('title','Create Category')

@section('content')
@php
    $rootIdInt = (int) request()->get('root_id', 0);
    $defaultParentIdInt = (int) ($defaultParentId ?? 0);

    if ($rootIdInt === 0 && $defaultParentIdInt > 0) {
        $rootIdInt = $defaultParentIdInt;
    }

    $selectedParent = old('parent_id', (string)$defaultParentIdInt);

    $backUrl = route('admin.categories.index', $rootIdInt > 0 ? ['root_id' => $rootIdInt] : []);
@endphp

<div >
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px;">
        <div>
            <h2 class="a2-title" style="margin-bottom:4px;">إضافة قسم</h2>
            <div style="color:var(--muted);font-size:13px;">
                @if($rootIdInt > 0)
                    سيتم الرجوع تلقائياً إلى قسم رئيسي #{{ $rootIdInt }} بعد الحفظ.
                @else
                    يمكنك إنشاء قسم رئيسي أو فرعي.
                @endif
            </div>
        </div>

        <a href="{{ $backUrl }}" class="a2-btn a2-btn--ghost" style="text-decoration:none;">
            ← رجوع
        </a>
    </div>

    @if($errors->any())
        <div style="background:#ffe8e8;border:1px solid #f2b6b6;padding:12px;border-radius:12px;margin:10px 0;">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST"
          action="{{ route('admin.categories.store') }}"
          enctype="multipart/form-data">
        @csrf

        {{-- Keep root_id so controller can redirect back to same root --}}
        <input type="hidden" name="root_id" value="{{ $rootIdInt }}">

        {{-- Names --}}
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <label style="display:block;">
                <div style="font-weight:800;margin-bottom:6px;">الاسم عربي <span style="color:#c00;">*</span></div>
                <input name="name_ar"
                       value="{{ old('name_ar') }}">
            </label>

            <label style="display:block;">
                <div style="font-weight:800;margin-bottom:6px;">الاسم إنجليزي</div>
                <input name="name_en"
                       value="{{ old('name_en') }}">
            </label>
        </div>

        {{-- Parent + Status --}}
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <label style="display:block;">
                <div style="font-weight:800;margin-bottom:6px;">المستوى / الأب</div>
                <select name="parent_id">
                    <option value="0" @selected((string)$selectedParent==='0')>
                        Root (قسم رئيسي)
                    </option>

                    @foreach($parents as $p)
                        <option value="{{ $p->id }}" @selected((string)$selectedParent === (string)$p->id)>
                            Child of: #{{ $p->id }} - {{ $p->name_ar ?: $p->name_en }}
                        </option>
                    @endforeach
                </select>

                <div style="color:var(--muted);font-size:12px;margin-top:6px;">
                    لو اخترت Root سيكون قسم رئيسي — لو اخترت أب سيكون قسم فرعي تابع له.
                </div>
            </label>

            <label style="display:block;">
                <div style="font-weight:800;margin-bottom:6px;">الحالة</div>
                <select name="is_active">
                    <option value="1" @selected((string)old('is_active','1')==='1')>Active</option>
                    <option value="0" @selected((string)old('is_active')==='0')>Inactive</option>
                </select>
            </label>
        </div>

        {{-- Prices + Reorder --}}
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
            <label style="display:block;">
                <div style="font-weight:800;margin-bottom:6px;">السعر الشهري</div>
                <input name="per_month"
                       value="{{ old('per_month') }}"
                       inputmode="decimal"
                       placeholder="مثال: 99">
            </label>

            <label style="display:block;">
                <div style="font-weight:800;margin-bottom:6px;">السعر السنوي</div>
                <input name="per_year"
                       value="{{ old('per_year') }}"
                       inputmode="decimal"
                       placeholder="مثال: 999">
            </label>

            <label style="display:block;">
                <div style="font-weight:800;margin-bottom:6px;">الترتيب (reorder)</div>
                <input name="reorder"
                       value="{{ old('reorder') }}"
                       inputmode="numeric"
                       placeholder="0, 1, 2 ...">
            </label>
        </div>

        {{-- Image upload --}}
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;align-items:start;">
            <label style="display:block;">
                <div style="font-weight:800;margin-bottom:6px;">صورة القسم (Upload)</div>
                <input type="file"
                       name="image"
                       accept="image/*">
                <div style="color:var(--muted);font-size:12px;margin-top:6px;">
                    JPG/PNG/WEBP بحد أقصى 2MB
                </div>
            </label>

            <div>
                <div style="font-weight:800;margin-bottom:6px;">المعاينة</div>
                <div id="imgPreviewBox">
                    <span style="color:var(--muted);font-size:12px;">اختر صورة</span>
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div style="display:flex;gap:10px;align-items:center;margin-top:6px;flex-wrap:wrap;">
            <button type="submit"
                    class="a2-btn">
                حفظ
            </button>

            <a href="{{ $backUrl }}" class="a2-btn a2-btn--ghost" style="text-decoration:none;">
                رجوع
            </a>
        </div>
    </form>
</div>

{{-- ✅ Preview script (no deps) --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.querySelector('input[type="file"][name="image"]');
    const box = document.getElementById('imgPreviewBox');

    if (!input || !box) return;

    input.addEventListener('change', function () {
        box.innerHTML = '';
        const file = input.files && input.files[0];
        if (!file) {
            box.innerHTML = '<span style="color:var(--muted);font-size:12px;">اختر صورة</span>';
            return;
        }

        const img = document.createElement('img');
        img.style.width = '100%';
        img.style.height = '100%';
        img.style.objectFit = 'cover';
        img.alt = 'preview';

        const url = URL.createObjectURL(file);
        img.src = url;
        box.appendChild(img);
    });
});
</script>
@endsection
