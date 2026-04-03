@extends('admin-v2.layouts.master')

@section('title','Category Children')
@section('body_class','admin-v2 admin-v2-category-children-index')

@section('content')
@php
    $parentIdInt = (int) ($parentId ?? 0);
    $qVal = (string) ($q ?? '');
    $perPageVal = (int) ($perPage ?? 50);
    $sortNow = (string) ($sort ?? 'reorder');
    $dirNow  = (string) ($dir ?? 'asc');

    $rowsSafe = $rows ?? collect();
    $selectedIdsSafe = collect($selectedChildIds ?? [])
        ->map(fn ($id) => (int) $id)
        ->filter(fn ($id) => $id > 0)
        ->values();

    $selectedRowsOnPage = collect($rowsSafe instanceof \Illuminate\Contracts\Pagination\Paginator ? $rowsSafe->items() : $rowsSafe)
        ->filter(fn ($row) => $selectedIdsSafe->contains((int) $row->id))
        ->values();

    $selectedCount = $selectedIdsSafe->count();
    $allCount = method_exists($rowsSafe, 'total') ? (int) $rowsSafe->total() : collect($rowsSafe)->count();

    $qsKeep = [
        'parent_id' => $parentIdInt,
        'q' => $qVal,
        'per_page' => $perPageVal,
        'sort' => $sortNow,
        'dir' => $dirNow,
    ];

    $sortUrl = function (string $col) use ($qsKeep, $sortNow, $dirNow) {
        $nextDir = ($sortNow === $col && $dirNow === 'asc') ? 'desc' : 'asc';

        return route('admin.category-children.index', array_merge($qsKeep, [
            'sort' => $col,
            'dir' => $nextDir,
        ]));
    };

    $arrow = function (string $col) use ($sortNow, $dirNow) {
        if ($sortNow !== $col) {
            return '';
        }

        return $dirNow === 'asc' ? ' ▲' : ' ▼';
    };

    $parentName = $parent?->name_ar ?: ($parent?->name_en ?: null);
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">الأقسام الفرعية العامة</h1>
            <div class="a2-page-subtitle">
                @if($parentIdInt > 0 && $parentName)
                    إدارة ربط الأقسام الفرعية بالقسم الرئيسي: {{ $parentName }}
                @else
                    إدارة الأقسام الفرعية العامة في النظام
                @endif
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.categories.index', $parentIdInt > 0 ? ['root_id' => $parentIdInt] : []) }}"
               class="a2-btn a2-btn-ghost">
                رجوع للأقسام
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="a2-stat-grid a2-mb-16">
        <div class="a2-stat-card">
            <div class="a2-stat-label">القسم الرئيسي الحالي</div>
            <div class="a2-stat-value">{{ $parentIdInt > 0 ? '#'.$parentIdInt : '—' }}</div>
            <div class="a2-stat-note">{{ $parentName ?: 'لم يتم اختيار قسم رئيسي' }}</div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">المحدد لهذا القسم</div>
            <div class="a2-stat-value" id="selectedCountText">{{ $selectedCount }}</div>
            <div class="a2-stat-note">عدد الأقسام المرتبطة حاليًا</div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">المعروض الآن</div>
            <div class="a2-stat-value">{{ $allCount }}</div>
            <div class="a2-stat-note">بعد الفلترة والبحث</div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">وضع الشاشة</div>
            <div class="a2-stat-value">{{ $parentIdInt > 0 ? 'Sync' : 'Browse' }}</div>
            <div class="a2-stat-note">
                {{ $parentIdInt > 0 ? 'اختيار وحفظ الربط' : 'عرض عام فقط' }}
            </div>
        </div>
    </div>

    <div class="a2-card a2-card--section a2-mb-16">
        <div class="a2-card-head">
            <div>
                <div class="a2-section-title a2-mb-0">إضافة قسم فرعي عام جديد</div>
                <div class="a2-section-subtitle">
                    سيتم إضافته للنظام، وإذا كنت داخل قسم رئيسي سيتم ربطه به مباشرة
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.category-children.store') }}" class="a2-form-grid a2-mt-12">
            @csrf

            <input type="hidden" name="return_to" value="category-children-index">

            @if($parentIdInt > 0)
                <input type="hidden" name="parent_ids[]" value="{{ $parentIdInt }}">
            @endif

            <div>
                <label class="a2-label">الاسم عربي</label>
                <input type="text" name="name_ar" class="a2-input" required>
            </div>

            <div>
                <label class="a2-label">الاسم إنجليزي</label>
                <input type="text" name="name_en" class="a2-input">
            </div>

            <div>
                <label class="a2-label">الترتيب</label>
                <input type="number" name="reorder" class="a2-input" min="0" value="0">
            </div>

            <div class="a2-page-actions" style="align-items:flex-end;">
                <button type="submit" class="a2-btn a2-btn-primary">
                    + إضافة القسم الفرعي
                </button>
            </div>
        </form>
    </div>

    <div class="a2-card a2-mb-16">
        <form method="GET" action="{{ route('admin.category-children.index') }}" class="a2-filterbar">
            <input type="text"
                   name="q"
                   value="{{ $qVal }}"
                   class="a2-input a2-filter-search"
                   placeholder="بحث داخل الأقسام الفرعية">

            <select name="parent_id" class="a2-select a2-filter-md">
                <option value="0">بدون قسم رئيسي محدد</option>
                @foreach(($parents ?? []) as $p)
                    <option value="{{ $p->id }}" @selected($parentIdInt === (int) $p->id)>
                        #{{ $p->id }} - {{ $p->name_ar ?: ($p->name_en ?: '—') }}
                    </option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-sm" name="sort">
                <option value="reorder" @selected($sortNow === 'reorder')>الترتيب</option>
                <option value="id" @selected($sortNow === 'id')>ID</option>
                <option value="name_ar" @selected($sortNow === 'name_ar')>الاسم العربي</option>
                <option value="name_en" @selected($sortNow === 'name_en')>الاسم الإنجليزي</option>
            </select>

            <select class="a2-select a2-filter-sm" name="dir">
                <option value="asc" @selected($dirNow === 'asc')>ASC</option>
                <option value="desc" @selected($dirNow === 'desc')>DESC</option>
            </select>

            <select class="a2-select a2-filter-sm" name="per_page">
                @foreach(($perPageOptions ?? []) as $n)
                    <option value="{{ $n }}" @selected($perPageVal === (int) $n)>{{ $n }} / صفحة</option>
                @endforeach
            </select>

            <div class="a2-filter-actions">
                <button type="submit" class="a2-btn a2-btn-primary">تطبيق</button>

                <a href="{{ route('admin.category-children.index', $parentIdInt > 0 ? ['parent_id' => $parentIdInt] : []) }}"
                   class="a2-btn a2-btn-ghost">
                    تفريغ
                </a>
            </div>
        </form>
    </div>

    @if($parentIdInt > 0)
        <div class="a2-card a2-card--soft a2-mb-16">
            <div class="a2-section-title">المحدد حاليًا لهذا القسم الرئيسي</div>
            <div class="a2-section-subtitle">يمكنك الإضافة أو الإلغاء من القائمة بالأسفل ثم الحفظ</div>

            <div class="a2-option-chip-grid a2-mt-12">
                @forelse($selectedRowsOnPage as $row)
                    <div class="a2-option-chip-card">
                        <div class="a2-option-chip-title">
                            {{ $row->name_ar ?: ($row->name_en ?: ('#'.$row->id)) }}
                        </div>
                        <div class="a2-option-chip-sub">
                            #{{ $row->id }} — خيارات: {{ (int) ($row->options_count ?? 0) }}
                        </div>
                    </div>
                @empty
                    <div class="a2-alert a2-alert-warning">
                        لا توجد عناصر محددة ضمن الصفحة الحالية. قد يكون هناك عناصر محددة في صفحات أخرى إن كنت تستخدم pagination.
                    </div>
                @endforelse
            </div>
        </div>
    @endif

    <form method="POST"
          action="{{ $parentIdInt > 0 ? route('admin.category-children.sync', ['parent' => $parentIdInt]) : '#' }}"
          class="a2-card">
        @csrf

        <input type="hidden" name="q" value="{{ $qVal }}">
        <input type="hidden" name="per_page" value="{{ $perPageVal }}">
        <input type="hidden" name="sort" value="{{ $sortNow }}">
        <input type="hidden" name="dir" value="{{ $dirNow }}">

        <div class="a2-card-head">
            <div>
                <div class="a2-section-title a2-mb-0">كل الأقسام الفرعية العامة</div>
                <div class="a2-section-subtitle">
                    @if($parentIdInt > 0)
                        اختر ما تريد ربطه أو إلغاء ربطه ثم اضغط حفظ
                    @else
                        اختر قسمًا رئيسيًا أولًا لتفعيل الحفظ
                    @endif
                </div>
            </div>

            <div class="a2-page-actions">
                @if($parentIdInt > 0)
                    <button type="submit" class="a2-btn a2-btn-primary">
                        حفظ الربط
                    </button>
                @endif
            </div>
        </div>

        <div class="a2-resultsbar">
            <div class="a2-resultsbar-meta">
                <strong>الإجمالي:</strong>
                <span>{{ $allCount }}</span>
            </div>

            <div class="a2-resultsbar-links">
                <button type="button" class="a2-resultsbar-btn" id="selectVisibleBtn">تحديد الظاهر</button>
                <button type="button" class="a2-resultsbar-btn" id="clearVisibleBtn">إلغاء الظاهر</button>
            </div>
        </div>

        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                <tr>
                    <th style="width:56px;">
                        <input type="checkbox" id="checkAllVisible">
                    </th>
                    <th style="width:90px;">
                        <a class="a2-link" href="{{ $sortUrl('id') }}">ID{!! $arrow('id') !!}</a>
                    </th>
                    <th>
                        <a class="a2-link" href="{{ $sortUrl('name_ar') }}">الاسم (AR){!! $arrow('name_ar') !!}</a>
                    </th>
                    <th>
                        <a class="a2-link" href="{{ $sortUrl('name_en') }}">الاسم (EN){!! $arrow('name_en') !!}</a>
                    </th>
                    <th style="width:110px;">مرتبط مع</th>
                    <th style="width:110px;">خياراته</th>
                    <th style="width:220px;">الإجراءات</th>
                </tr>
                </thead>

                <tbody>
                @forelse(($rowsSafe ?? []) as $row)
                    @php
                        $isChecked = $selectedIdsSafe->contains((int) $row->id);
                    @endphp
                    <tr>
                        <td>
                            <input type="checkbox"
                                   class="row-checkbox"
                                   name="child_ids[]"
                                   value="{{ $row->id }}"
                                   @checked($isChecked)
                                   {{ $parentIdInt === 0 ? 'disabled' : '' }}>
                        </td>

                        <td>#{{ $row->id }}</td>

                        <td class="a2-fw-700">
                            {{ $row->name_ar ?: '—' }}
                        </td>

                        <td dir="ltr">
                            {{ $row->name_en ?: '—' }}
                        </td>

                        <td>
                            <span class="a2-pill a2-pill-active">{{ (int) ($row->parents_count ?? 0) }}</span>
                        </td>

                        <td>
                            <span class="a2-pill a2-pill-success">{{ (int) ($row->options_count ?? 0) }}</span>
                        </td>

                        <td>
                            <div class="a2-actions">
                                <a href="{{ route('admin.category-children.edit', ['categoryChild' => $row->id, 'parent_id' => $parentIdInt]) }}"
                                   class="a2-btn a2-btn-ghost a2-btn-sm">
                                    تعديل البيانات
                                </a>

                                <a href="{{ route('admin.category-child-options.edit', ['categoryChild' => $row->id, 'parent_id' => $parentIdInt]) }}"
                                   class="a2-btn a2-btn-primary a2-btn-sm">
                                    إدارة الخيارات
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="a2-empty-cell">لا توجد أقسام فرعية</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if(!empty($rowsSafe) && method_exists($rowsSafe, 'links'))
            <div class="a2-mt-16">
                {{ $rowsSafe->links() }}
            </div>
        @endif

        @if($parentIdInt > 0)
            <div class="a2-page-actions a2-mt-16" style="justify-content:flex-end;">
                <button type="submit" class="a2-btn a2-btn-primary">
                    حفظ الربط
                </button>
            </div>
        @endif
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const checkAllVisible = document.getElementById('checkAllVisible');
    const rowCheckboxes = Array.from(document.querySelectorAll('.row-checkbox:not([disabled])'));
    const selectedCountText = document.getElementById('selectedCountText');
    const selectVisibleBtn = document.getElementById('selectVisibleBtn');
    const clearVisibleBtn = document.getElementById('clearVisibleBtn');

    function refreshSelectedCount() {
        if (!selectedCountText) return;
        selectedCountText.textContent = rowCheckboxes.filter(cb => cb.checked).length;
    }

    if (checkAllVisible) {
        checkAllVisible.addEventListener('change', function () {
            rowCheckboxes.forEach(function (cb) {
                cb.checked = checkAllVisible.checked;
            });
            refreshSelectedCount();
        });
    }

    rowCheckboxes.forEach(function (cb) {
        cb.addEventListener('change', refreshSelectedCount);
    });

    if (selectVisibleBtn) {
        selectVisibleBtn.addEventListener('click', function () {
            rowCheckboxes.forEach(function (cb) {
                cb.checked = true;
            });
            refreshSelectedCount();
        });
    }

    if (clearVisibleBtn) {
        clearVisibleBtn.addEventListener('click', function () {
            rowCheckboxes.forEach(function (cb) {
                cb.checked = false;
            });
            refreshSelectedCount();
        });
    }

    refreshSelectedCount();
});
</script>
@endpush
@endsection