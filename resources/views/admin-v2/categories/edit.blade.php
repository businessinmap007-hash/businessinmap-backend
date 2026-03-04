@extends('admin-v2.layouts.master')

@section('title','Edit Category')

@section('content')
@php
    $rootIdInt = (int) request()->get('root_id', 0);

    if ($rootIdInt === 0 && (int)($category->parent_id ?? 0) > 0) {
        $rootIdInt = (int) $category->parent_id;
    }

    $isRoot  = ((int)($category->parent_id ?? 0) === 0);
    $imgPath = $category->image ?? null;

    // ✅ Back URL (يرجع لنفس root_id)
    $backUrl = route('admin.categories.index', $rootIdInt > 0 ? ['root_id' => $rootIdInt] : []);
@endphp

<div class="a2-page" style="max-width:1000px;margin:0 auto;">
    <div class="a2-card">

        {{-- Header --}}
        <div class="a2-header" style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
            <div style="display:flex;gap:12px;align-items:flex-start;">
                <x-admin-v2.image :path="$imgPath" size="56" radius="14px" />

                <div>
                    <h2 class="a2-title" style="margin:0;">
                        تعديل قسم <span class="a2-muted">#{{ $category->id }}</span>
                    </h2>

                    <div class="a2-hint" style="margin-top:6px;">
                        النوع:
                        <b>
                            @if($isRoot)
                                قسم رئيسي
                            @else
                                قسم فرعي
                            @endif
                        </b>
                    </div>
                </div>
            </div>

            <div style="display:flex;gap:10px;">
                <a href="{{ $backUrl }}" class="a2-btn a2-btn-ghost">← رجوع</a>
            </div>
        </div>

        {{-- Alerts --}}
        @if(session('success'))
            <div class="a2-alert a2-alert-success" style="margin-top:12px;">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="a2-alert a2-alert-danger" style="margin-top:12px;">{{ $errors->first() }}</div>
        @endif

        <form method="POST"
              action="{{ route('admin.categories.update', $category->id) }}"
              enctype="multipart/form-data"
              style="margin-top:14px;">
            @csrf
            @method('PUT')

            {{-- Keep root_id so controller can redirect back to same root --}}
            <input type="hidden" name="root_id" value="{{ $rootIdInt }}">

            {{-- ===== Section: Basic ===== --}}
            <div class="a2-card" style="margin-top:12px;">
                <div class="a2-card-head">
                    <div class="a2-card-title">البيانات الأساسية</div>
                    <div class="a2-card-sub">الاسم</div>
                </div>

                <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;">
                    <div>
                        <label class="a2-label">الاسم عربي <span class="a2-danger">*</span></label>
                        <input class="a2-input" name="name_ar" value="{{ old('name_ar', $category->name_ar) }}">
                    </div>

                    <div>
                        <label class="a2-label">الاسم إنجليزي</label>
                        <input class="a2-input" name="name_en" value="{{ old('name_en', $category->name_en) }}" dir="ltr">
                    </div>

                    <div>
                        <label class="a2-label">المستوى </label>
                        <select class="a2-select" name="parent_id">
                            <option value="0" @selected((string)old('parent_id', (string)$category->parent_id)==='0')>
                                Root (قسم رئيسي)
                            </option>

                            @foreach($parents as $p)
                                <option value="{{ $p->id }}"
                                    @selected((string)old('parent_id', (string)$category->parent_id) === (string)$p->id)>
                                    Child of: #{{ $p->id }} - {{ $p->name_ar ?: ($p->name_en ?: '—') }}
                                </option>
                            @endforeach
                        </select>

                        <div class="a2-hint" style="margin-top:6px;">
                            ملاحظة: لا يمكن جعل القسم تابعًا لنفسه (محمي في Controller).
                        </div>
                    </div>

                    <div>
                        <label class="a2-label">الحالة</label>
                        <select class="a2-select" name="is_active">
                            <option value="1" @selected((string)old('is_active', (string)$category->is_active)==='1')>Active</option>
                            <option value="0" @selected((string)old('is_active', (string)$category->is_active)==='0')>Inactive</option>
                        </select>
                    </div>
                </div>
            </div>

            {{-- ===== Section: Prices + Order ===== --}}
            <div class="a2-card" style="margin-top:12px;">
                <div class="a2-card-head">
                    <div class="a2-card-title">الأسعار والترتيب</div>
                </div>

                <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;">
                    <div>
                        <label class="a2-label">السعر الشهري</label>
                        <input class="a2-input" name="per_month"
                               value="{{ old('per_month', $category->per_month) }}"
                               inputmode="decimal">
                    </div>

                    <div>
                        <label class="a2-label">السعر السنوي</label>
                        <input class="a2-input" name="per_year"
                               value="{{ old('per_year', $category->per_year) }}"
                               inputmode="decimal">
                    </div>

                    <div>
                        <label class="a2-label">الترتيب (reorder)</label>
                        <input class="a2-input" name="reorder"
                               value="{{ old('reorder', $category->reorder) }}"
                               inputmode="numeric">
                    </div>
                </div>
            </div>

            {{-- ===== Section: Image ===== --}}
            <div class="a2-card" style="margin-top:12px;">
                <div class="a2-card-head">
                    <div class="a2-card-title">صورة القسم</div>
                    <div class="a2-card-sub">Upload + Preview</div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;align-items:start;">
                    <div>
                        <label class="a2-label">صورة القسم (Upload)</label>
                        <input class="a2-input" type="file" name="image" accept="image/*">
                        <div class="a2-hint" style="margin-top:6px;"></div>
                    </div>

                    <div>
                        <label class="a2-label">المعاينة</label>
                        <div class="a2-card" style="padding:14px;text-align:center;">
                            <x-admin-v2.image :path="$imgPath" size="140" radius="16px" />
                            <div class="a2-muted" style="margin-top:8px;word-break:break-all;">
                              
                            </div>
                        </div>
                    </div>
                </div>

                @if($isRoot)
                    <div class="a2-alert a2-alert-warning" style="margin-top:12px;">
                        هذا قسم رئيسي — عادةً أسعار الاشتراك والصورة تكون عليه. يمكنك تعديلها هنا مباشرة.
                    </div>
                @endif
            </div>

            {{-- Actions --}}
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:14px;">
                <a href="{{ $backUrl }}" class="a2-btn a2-btn-ghost">رجوع</a>
                <button type="submit" class="a2-btn a2-btn-primary">تحديث</button>
            </div>

        </form>
    </div>
</div>



@endsection
