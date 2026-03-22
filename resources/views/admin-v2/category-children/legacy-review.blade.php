@extends('admin-v2.layouts.master')

@section('title','Legacy Category Children Review')
@section('body_class','admin-v2 admin-v2-category-children-legacy-review')

@section('content')
@php
    $qVal = (string) ($q ?? '');
    $perPageVal = (int) ($perPage ?? 50);

    $groupedItems = collect($items ?? [])->groupBy(function ($item) {
        $legacy = $item['legacy'] ?? null;
        return (int) ($legacy->parent_id ?? 0);
    });

    $groupedItems = $groupedItems->map(function ($group) {
        $first = $group->first();
        $legacy = $first['legacy'] ?? null;
        $parent = $legacy?->parent;

        return [
            'parent' => $parent,
            'rows' => $group->values(),
            'count' => $group->count(),
            'matched_count' => $group->where('is_matched', true)->count(),
            'new_count' => $group->where('is_matched', false)->count(),
        ];
    })->sortKeys();
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">مراجعة الأقسام الفرعية القديمة</h1>
            <div class="a2-page-subtitle">
                استعراض الفروع القديمة من جدول categories وتجميعها حسب القسم الرئيسي قبل الاستيراد
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.category-children.index') }}" class="a2-btn a2-btn-ghost">
                رجوع إلى الأقسام الفرعية
            </a>
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

    <div class="a2-stat-grid a2-mb-16">
        <div class="a2-stat-card">
            <div class="a2-stat-label">إجمالي العناصر القديمة</div>
            <div class="a2-stat-value">{{ collect($items ?? [])->count() }}</div>
            <div class="a2-stat-note">المعروضة الآن</div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">عدد الأقسام الرئيسية</div>
            <div class="a2-stat-value">{{ $groupedItems->count() }}</div>
            <div class="a2-stat-note">مقسمة حسب parent</div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">مطابق موجود</div>
            <div class="a2-stat-value">{{ collect($items ?? [])->where('is_matched', true)->count() }}</div>
            <div class="a2-stat-note">لن يُنشأ child جديد</div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">سيُنشأ جديد</div>
            <div class="a2-stat-value">{{ collect($items ?? [])->where('is_matched', false)->count() }}</div>
            <div class="a2-stat-note">داخل category_children_master</div>
        </div>
    </div>

    <div class="a2-card">
       <form method="GET" action="{{ route('admin.category-children.legacy-review') }}" class="a2-filterbar">
    <input class="a2-input a2-filter-search"
           type="text"
           name="q"
           value="{{ $qVal }}"
           placeholder="بحث داخل الأقسام الفرعية القديمة">

    {{-- 👇 فلتر القسم الرئيسي --}}
    <select class="a2-select a2-filter-md" name="parent_id">
        <option value="0">كل الأقسام الرئيسية</option>
        @foreach(($parents ?? []) as $p)
            <option value="{{ $p->id }}" @selected((int) ($parentId ?? 0) === (int) $p->id)>
                #{{ $p->id }} - {{ $p->name_ar ?: ($p->name_en ?: '—') }}
            </option>
        @endforeach
    </select>

    <select class="a2-select a2-filter-sm" name="per_page">
        @foreach(($perPageOptions ?? []) as $n)
            <option value="{{ $n }}" @selected((int) $perPageVal === (int) $n)>
                {{ $n }} / صفحة
            </option>
        @endforeach
    </select>

    <div class="a2-filter-actions">
        <button type="submit" class="a2-btn a2-btn-primary">تطبيق</button>

        <a href="{{ route('admin.category-children.legacy-review') }}"
           class="a2-btn a2-btn-ghost">
            تفريغ
        </a>
    </div>
</form>
    </div>

    <form method="POST" action="{{ route('admin.category-children.legacy-import') }}">
        @csrf

        <div class="a2-card a2-card--section a2-mt-16">
            <div class="a2-card-head">
                <div>
                    <div class="a2-section-title a2-mb-0">أوامر الاستيراد</div>
                    <div class="a2-section-subtitle">
                        يمكنك تحديد الكل أو تحديد مجموعات رئيسية كاملة أو عناصر منفردة
                    </div>
                </div>

                <div class="a2-page-actions">
                    <button type="button" class="a2-btn a2-btn-ghost" id="check-all-legacy">
                        تحديد الكل
                    </button>
                    <button type="button" class="a2-btn a2-btn-ghost" id="uncheck-all-legacy">
                        إلغاء الكل
                    </button>
                    <button type="submit" class="a2-btn a2-btn-primary">
                        استيراد المحدد
                    </button>
                </div>
            </div>
        </div>

        @forelse($groupedItems as $parentId => $group)
            @php
                $parent = $group['parent'];
                $rowsInGroup = $group['rows'];
                $groupKey = 'parent-group-' . $parentId;
            @endphp

            <div class="a2-card a2-card--section a2-mt-16 js-parent-group" data-group="{{ $groupKey }}">
                <div class="a2-card-head">
                    <div>
                        <div class="a2-section-title a2-mb-0">
                            {{ $parent ? ($parent->name_ar ?: ($parent->name_en ?: ('#' . $parent->id))) : 'قسم رئيسي غير موجود' }}
                            <span class="a2-muted">#{{ $parentId }}</span>
                        </div>

                        <div class="a2-section-subtitle">
                            إجمالي العناصر: {{ $group['count'] }}
                            —
                            مطابق موجود: {{ $group['matched_count'] }}
                            —
                            سيُنشأ جديد: {{ $group['new_count'] }}
                        </div>
                    </div>

                    <div class="a2-page-actions">
                        <button type="button"
                                class="a2-btn a2-btn-ghost a2-btn-sm js-check-group"
                                data-group="{{ $groupKey }}">
                            تحديد المجموعة
                        </button>

                        <button type="button"
                                class="a2-btn a2-btn-ghost a2-btn-sm js-uncheck-group"
                                data-group="{{ $groupKey }}">
                            إلغاء المجموعة
                        </button>
                    </div>
                </div>

                <div class="a2-table-wrap">
                    <table class="a2-table">
                        <thead>
                        <tr>
                            <th style="width:70px;">اختر</th>
                            <th style="width:90px;">Legacy ID</th>
                            <th style="width:120px;">Reorder</th>
                            <th>الاسم (AR)</th>
                            <th>الاسم (EN)</th>
                            <th style="width:160px;">حالة المطابقة</th>
                            <th>المطابقة الحالية</th>
                        </tr>
                        </thead>

                        <tbody>
                        @foreach($rowsInGroup as $item)
                            @php
                                $legacy = $item['legacy'];
                                $matched = $item['matched_child'];
                                $isMatched = (bool) $item['is_matched'];
                            @endphp
                            <tr>
                                <td>
                                    <input type="checkbox"
                                           class="js-legacy-checkbox js-group-checkbox"
                                           data-group="{{ $groupKey }}"
                                           name="legacy_ids[]"
                                           value="{{ $legacy->id }}">
                                </td>

                                <td>{{ $legacy->id }}</td>
                                <td>{{ (int) ($legacy->reorder ?? 0) }}</td>
                                <td class="a2-fw-700">{{ $legacy->name_ar ?: '—' }}</td>
                                <td dir="ltr">{{ $legacy->name_en ?: '—' }}</td>

                                <td>
                                    @if($isMatched)
                                        <span class="a2-pill a2-pill-success">مطابق موجود</span>
                                    @else
                                        <span class="a2-pill a2-pill-gray">سيُنشأ جديد</span>
                                    @endif
                                </td>

                                <td>
                                    @if($matched)
                                        <div class="a2-option-chip-card" style="padding:10px;">
                                            <div class="a2-option-chip-title">
                                                {{ $matched->name_ar ?: ($matched->name_en ?: ('#' . $matched->id)) }}
                                            </div>
                                            <div class="a2-option-chip-sub" dir="ltr">
                                                #{{ $matched->id }}
                                                @if(!empty($matched->name_en))
                                                    — {{ $matched->name_en }}
                                                @endif
                                                — reorder: {{ (int) ($matched->reorder ?? 0) }}
                                            </div>
                                        </div>
                                    @else
                                        <span class="a2-muted">لا توجد مطابقة حالية</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <div class="a2-card a2-mt-16">
                <div class="a2-empty-cell">
                    لا توجد أقسام فرعية قديمة للمراجعة
                </div>
            </div>
        @endforelse

        @if(isset($rows) && method_exists($rows, 'links'))
            <div class="a2-mt-16">
                {{ $rows->links() }}
            </div>
        @endif
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const checkAllBtn = document.getElementById('check-all-legacy');
    const uncheckAllBtn = document.getElementById('uncheck-all-legacy');

    function allChecks() {
        return document.querySelectorAll('.js-legacy-checkbox');
    }

    function groupChecks(groupKey) {
        return document.querySelectorAll('.js-group-checkbox[data-group="' + groupKey + '"]');
    }

    if (checkAllBtn) {
        checkAllBtn.addEventListener('click', function () {
            allChecks().forEach(function (cb) {
                cb.checked = true;
            });
        });
    }

    if (uncheckAllBtn) {
        uncheckAllBtn.addEventListener('click', function () {
            allChecks().forEach(function (cb) {
                cb.checked = false;
            });
        });
    }

    document.querySelectorAll('.js-check-group').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const groupKey = this.dataset.group;
            groupChecks(groupKey).forEach(function (cb) {
                cb.checked = true;
            });
        });
    });

    document.querySelectorAll('.js-uncheck-group').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const groupKey = this.dataset.group;
            groupChecks(groupKey).forEach(function (cb) {
                cb.checked = false;
            });
        });
    });
});
</script>
@endpush
@endsection