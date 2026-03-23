@extends('admin-v2.layouts.master')

@section('title','Category Child Option Groups')
@section('body_class','admin-v2 admin-v2-category-child-option-groups-index')

@section('content')
@php
    $parentIdInt = (int) ($parentId ?? 0);
    $qVal = (string) ($q ?? '');
    $activeVal = (string) ($active ?? '');
    $perPageVal = (int) ($perPage ?? 50);

    $groupsSafe = $groups ?? collect();

    $groupsCount = method_exists($groupsSafe, 'total')
        ? $groupsSafe->total()
        : collect($groupsSafe)->count();

    $childName = $categoryChild->name_ar ?: ($categoryChild->name_en ?: ('#' . $categoryChild->id));
    $parentName = !empty($parent)
        ? ($parent->name_ar ?: ($parent->name_en ?: ('#' . $parent->id)))
        : null;
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">مجموعات خيارات القسم الفرعي</h1>
            <div class="a2-page-subtitle">
                {{ $childName }}
                @if($parentName)
                    <span class="a2-muted">| القسم الرئيسي: {{ $parentName }}</span>
                @endif
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.category-child-option-groups.create', ['categoryChild' => $categoryChild->id, 'parent_id' => $parentIdInt]) }}"
               class="a2-btn a2-btn-primary">
                + إضافة مجموعة
            </a>

            <a href="{{ route('admin.category-child-options.edit', ['categoryChild' => $categoryChild->id, 'parent_id' => $parentIdInt]) }}"
               class="a2-btn a2-btn-ghost">
                خيارات القسم الفرعي
            </a>

            <a href="{{ route('admin.category-children.index', ['parent_id' => $parentIdInt]) }}"
               class="a2-btn a2-btn-ghost">
                رجوع للأقسام الفرعية
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
            <div class="a2-stat-label">القسم الفرعي</div>
            <div class="a2-stat-value">#{{ $categoryChild->id }}</div>
            <div class="a2-stat-note">{{ $childName }}</div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">عدد المجموعات</div>
            <div class="a2-stat-value">{{ $groupsCount }}</div>
            <div class="a2-stat-note">ضمن النتائج الحالية</div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">القسم الرئيسي</div>
            <div class="a2-stat-value">{{ $parentIdInt > 0 ? '#'.$parentIdInt : '—' }}</div>
            <div class="a2-stat-note">{{ $parentName ?: 'غير محدد' }}</div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">نوع التنظيم</div>
            <div class="a2-stat-value">Groups</div>
            <div class="a2-stat-note">تنظيم بصري للخيارات</div>
        </div>
    </div>

    <div class="a2-card">
        <form method="GET"
              action="{{ route('admin.category-child-option-groups.index', ['categoryChild' => $categoryChild->id]) }}"
              class="a2-filterbar">

            <input type="hidden" name="parent_id" value="{{ $parentIdInt }}">

            <input class="a2-input a2-filter-search"
                   type="text"
                   name="q"
                   value="{{ $qVal }}"
                   placeholder="بحث داخل مجموعات الخيارات">

            <select class="a2-select a2-filter-sm" name="active">
                @foreach(($activeOptions ?? []) as $k => $label)
                    <option value="{{ $k }}" @selected((string) $activeVal === (string) $k)>
                        {{ $label }}
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

                <a class="a2-btn a2-btn-ghost"
                   href="{{ route('admin.category-child-option-groups.index', ['categoryChild' => $categoryChild->id, 'parent_id' => $parentIdInt]) }}">
                    تفريغ
                </a>
            </div>
        </form>
    </div>

    <div class="a2-card a2-mt-16">
        <div class="a2-card-head">
            <div>
                <div class="a2-section-title a2-mb-0">قائمة المجموعات</div>
                <div class="a2-section-subtitle">
                    إدارة المجموعات التنظيمية الخاصة بخيارات هذا القسم الفرعي
                </div>
            </div>

            <div class="a2-page-actions">
                <a href="{{ route('admin.category-child-option-groups.create', ['categoryChild' => $categoryChild->id, 'parent_id' => $parentIdInt]) }}"
                   class="a2-btn a2-btn-primary a2-btn-sm">
                    + إضافة مجموعة
                </a>
            </div>
        </div>

        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                <tr>
                    <th style="width:90px;">ID</th>
                    <th>الاسم (AR)</th>
                    <th>الاسم (EN)</th>
                    <th style="width:120px;">الترتيب</th>
                    <th style="width:120px;">الحالة</th>
                    <th style="width:120px;">الخيارات</th>
                    <th style="width:320px;">الإجراءات</th>
                </tr>
                </thead>

                <tbody>
                @forelse($groupsSafe as $group)
                    @php
                        $isActive = (int) ($group->is_active ?? 0) === 1;
                        $optionsCount = (int) ($group->child_option_links_count ?? 0);
                    @endphp
                    <tr>
                        <td>{{ $group->id }}</td>
                        <td class="a2-fw-700">{{ $group->name_ar ?: '—' }}</td>
                        <td dir="ltr">{{ $group->name_en ?: '—' }}</td>
                        <td>{{ (int) ($group->reorder ?? 0) }}</td>

                        <td>
                            <span class="a2-pill {{ $isActive ? 'a2-pill-active' : 'a2-pill-inactive' }}">
                                {{ $isActive ? 'نشط' : 'غير نشط' }}
                            </span>
                        </td>

                        <td>
                            <span class="a2-pill a2-pill-success">{{ $optionsCount }}</span>
                        </td>

                      <td>
    <div class="a2-actions">
        <a href="{{ route('admin.category-child-option-groups.edit', ['categoryChild' => $categoryChild->id, 'group' => $group->id, 'parent_id' => $parentIdInt]) }}"
           class="a2-btn a2-btn-ghost a2-btn-sm">
            تعديل
        </a>

        <a href="{{ route('admin.category-child-options.edit', ['categoryChild' => $categoryChild->id, 'parent_id' => $parentIdInt, 'group_id' => $group->id]) }}"
           class="a2-btn a2-btn-primary a2-btn-sm">
            إدارة الخيارات
        </a>

        <form method="POST"
              action="{{ route('admin.category-child-option-groups.destroy', ['categoryChild' => $categoryChild->id, 'group' => $group->id]) }}"
              onsubmit="return confirm('تأكيد حذف المجموعة؟ سيتم نقل الخيارات إلى مجموعة أخرى.');">
            @csrf
            @method('DELETE')
            <input type="hidden" name="parent_id" value="{{ $parentIdInt }}">

            <button type="submit" class="a2-btn a2-btn-danger a2-btn-sm">
                حذف
            </button>
        </form>
    </div>
</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="a2-empty-cell">لا توجد مجموعات خيارات حتى الآن</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if(!empty($groupsSafe) && method_exists($groupsSafe, 'links'))
            <div class="a2-mt-16">
                {{ $groupsSafe->links() }}
            </div>
        @endif
    </div>
</div>
@endsection