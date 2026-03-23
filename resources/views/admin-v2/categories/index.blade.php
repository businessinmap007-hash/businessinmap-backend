@extends('admin-v2.layouts.master')

@section('title','Categories')
@section('body_class','admin-v2 admin-v2-categories-index')

@section('content')
@php
    $rootIdInt = (int) ($rootId ?? 0);
    $qVal = (string) ($q ?? '');
    $activeVal = (string) ($active ?? '');
    $perPageVal = (int) ($perPage ?? 50);

    $sortNow = (string) ($sort ?? 'reorder');
    $dirNow  = (string) ($dir ?? 'asc');

    $childrenSafe = $children ?? collect();

    $qsKeep = [
        'root_id'  => $rootIdInt,
        'q'        => $qVal,
        'active'   => $activeVal,
        'per_page' => $perPageVal,
        'sort'     => $sortNow,
        'dir'      => $dirNow,
    ];

    $sortUrl = function (string $col) use ($qsKeep, $sortNow, $dirNow) {
        $nextDir = ($sortNow === $col && $dirNow === 'asc') ? 'desc' : 'asc';

        return route('admin.categories.index', array_merge($qsKeep, [
            'sort' => $col,
            'dir'  => $nextDir,
        ]));
    };

    $arrow = function (string $col) use ($sortNow, $dirNow) {
        if ($sortNow !== $col) {
            return '';
        }

        return $dirNow === 'asc' ? ' ▲' : ' ▼';
    };

    $nameOf = function ($cat) {
        $ar = (string) ($cat->name_ar ?? '');
        $en = (string) ($cat->name_en ?? '');
        return $ar !== '' ? $ar : ($en !== '' ? $en : '—');
    };

    $rootsCount = collect($roots ?? [])->count();
    $childrenCount = method_exists($childrenSafe, 'total')
        ? $childrenSafe->total()
        : collect($childrenSafe)->count();
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">الأقسام</h1>
            <div class="a2-page-subtitle">
                إدارة الأقسام الرئيسية، والأقسام الفرعية الموحدة، ومراجعة الفروع القديمة قبل الاستيراد
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.categories.create') }}" class="a2-btn a2-btn-primary">
                + إضافة قسم رئيسي
            </a>

            <a href="{{ route('admin.category-children.index') }}" class="a2-btn a2-btn-ghost">
                إدارة كل الأقسام الفرعية
            </a>

            <a href="{{ route('admin.category-children.legacy-review') }}" class="a2-btn a2-btn-ghost">
                مراجعة الفروع القديمة
            </a>

            @if($rootIdInt > 0)
                <a href="{{ route('admin.category-children.create', ['parent_id' => $rootIdInt]) }}"
                   class="a2-btn a2-btn-primary">
                    + إضافة قسم فرعي موحّد
                </a>
            @endif
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
            <div class="a2-stat-label">عدد الأقسام الرئيسية</div>
            <div class="a2-stat-value">{{ $rootsCount }}</div>
            <div class="a2-stat-note">المسجلة بالنظام</div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">القسم الرئيسي المحدد</div>
            <div class="a2-stat-value">{{ $rootIdInt > 0 ? '#'.$rootIdInt : '—' }}</div>
            <div class="a2-stat-note">
                {{ $rootIdInt > 0 && !empty($root) ? $nameOf($root) : 'لا يوجد تحديد' }}
            </div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">عدد الفروع المعروضة</div>
            <div class="a2-stat-value">{{ $rootIdInt > 0 ? $childrenCount : 0 }}</div>
            <div class="a2-stat-note">ضمن العرض الحالي</div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">وضع العرض الحالي</div>
            <div class="a2-stat-value">{{ $rootIdInt > 0 ? 'Children' : 'Roots' }}</div>
            <div class="a2-stat-note">
                {{ $rootIdInt > 0 ? 'عرض الأقسام الفرعية الموحدة' : 'عرض الأقسام الرئيسية' }}
            </div>
        </div>
    </div>

    <div class="a2-card a2-card--section a2-mb-16">
        <div class="a2-card-head">
            <div>
                <div class="a2-section-title a2-mb-0">مراكز الإدارة</div>
                <div class="a2-section-subtitle">
                    تنقل سريع بين الشاشات الأساسية الخاصة بالأقسام والتنظيم الجديد
                </div>
            </div>
        </div>

        <div class="a2-option-chip-grid" style="grid-template-columns:repeat(auto-fit,minmax(260px,1fr));">
            <div class="a2-option-chip-card">
                <div class="a2-option-chip-title">الأقسام الرئيسية</div>
                <div class="a2-option-chip-sub">
                    إدارة بيانات القسم الرئيسي والخدمات المرتبطة به
                </div>
                <div class="a2-page-actions a2-mt-12">
                    <a href="{{ route('admin.categories.index') }}" class="a2-btn a2-btn-ghost a2-btn-sm">
                        فتح القائمة
                    </a>
                    <a href="{{ route('admin.categories.create') }}" class="a2-btn a2-btn-primary a2-btn-sm">
                        إضافة جديد
                    </a>
                </div>
            </div>

            <div class="a2-option-chip-card">
                <div class="a2-option-chip-title">الأقسام الفرعية الموحدة</div>
                <div class="a2-option-chip-sub">
                    إدارة الفروع الجديدة وربطها بالأقسام الرئيسية وترتيبها
                </div>
                <div class="a2-page-actions a2-mt-12">
                    <a href="{{ route('admin.category-children.index') }}" class="a2-btn a2-btn-ghost a2-btn-sm">
                        إدارة الفروع
                    </a>
                    @if($rootIdInt > 0)
                        <a href="{{ route('admin.category-children.create', ['parent_id' => $rootIdInt]) }}"
                           class="a2-btn a2-btn-primary a2-btn-sm">
                            إضافة لهذا القسم
                        </a>
                    @endif
                </div>
            </div>

            <div class="a2-option-chip-card">
                <div class="a2-option-chip-title">الفروع القديمة (Legacy)</div>
                <div class="a2-option-chip-sub">
                    مراجعة ما كان مخزنًا سابقًا داخل categories واستيراده للنظام الجديد
                </div>
                <div class="a2-page-actions a2-mt-12">
                    <a href="{{ route('admin.category-children.legacy-review') }}" class="a2-btn a2-btn-primary a2-btn-sm">
                        فتح شاشة المراجعة
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="a2-card">
        <form method="GET" action="{{ route('admin.categories.index') }}" class="a2-filterbar">
            <input class="a2-input a2-filter-search"
                   type="text"
                   name="q"
                   value="{{ $qVal }}"
                   placeholder="{{ $rootIdInt > 0 ? 'بحث داخل الأقسام الفرعية الموحّدة' : 'بحث داخل الأقسام الرئيسية' }}">

            <select class="a2-select a2-filter-md" name="root_id">
                <option value="0" @selected($rootIdInt === 0)>كل الأقسام الرئيسية</option>
                @foreach(($roots ?? []) as $r)
                    <option value="{{ $r->id }}" @selected($rootIdInt === (int) $r->id)>
                        #{{ $r->id }} - {{ $nameOf($r) }}
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
                   href="{{ route('admin.categories.index', $rootIdInt > 0 ? ['root_id' => $rootIdInt] : []) }}">
                    تفريغ
                </a>
            </div>
        </form>
    </div>

    @if($rootIdInt > 0 && !empty($root))
        <div class="a2-card a2-card--section a2-mt-16">
            <div class="a2-card-head">
                <div>
                    <div class="a2-section-title a2-mb-0">القسم الرئيسي المختار</div>
                    <div class="a2-section-subtitle">
                        معلومات القسم الذي يتم عرض الأقسام الفرعية الموحدة الخاصة به الآن
                    </div>
                </div>

                <div class="a2-page-actions">
                    <a class="a2-btn a2-btn-ghost"
                       href="{{ route('admin.categories.edit', ['category' => $root->id]) }}">
                        تعديل القسم الرئيسي
                    </a>

                    <a class="a2-btn a2-btn-primary"
                       href="{{ route('admin.category-children.create', ['parent_id' => $root->id]) }}">
                        + إضافة قسم فرعي موحّد
                    </a>
                </div>
            </div>

            <div class="a2-form-grid">
                <div style="display:flex;align-items:flex-start;gap:14px;">
                    <x-admin-v2.image :path="$root->image" size="68" radius="16px" />

                    <div>
                        <div class="a2-fw-900" style="font-size:18px;">
                            {{ $root->name_ar ?: ($root->name_en ?: '—') }}
                            <span class="a2-muted">#{{ $root->id }}</span>
                        </div>

                        @if(!empty($root->name_en))
                            <div class="a2-page-subtitle">
                                EN: {{ $root->name_en }}
                            </div>
                        @endif

                        @if(!empty($root->slug))
                            <div class="a2-page-subtitle">
                                Slug: <span dir="ltr">{{ $root->slug }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="a2-form-grid">
                    <div class="a2-card a2-card--soft">
                        <div class="a2-stat-label">السعر الشهري</div>
                        <div class="a2-stat-value">{{ $root->per_month ?? '—' }}</div>
                    </div>

                    <div class="a2-card a2-card--soft">
                        <div class="a2-stat-label">السعر السنوي</div>
                        <div class="a2-stat-value">{{ $root->per_year ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <form method="POST"
              action="{{ route('admin.category-children.reorder-bulk') }}"
              class="a2-card a2-mt-16">
            @csrf
            <input type="hidden" name="parent_id" value="{{ $rootIdInt }}">

            <div class="a2-card-head">
                <div>
                    <div class="a2-section-title a2-mb-0">الأقسام الفرعية الموحدة</div>
                    <div class="a2-section-subtitle">
                        يمكنك تعديل reorder لعنصر واحد أو عدة عناصر دفعة واحدة
                    </div>
                </div>

                <div class="a2-page-actions">
                    <button type="submit" class="a2-btn a2-btn-primary">
                        حفظ كل التعديلات
                    </button>

                    <a href="{{ route('admin.category-children.index', ['parent_id' => $rootIdInt]) }}"
                       class="a2-btn a2-btn-ghost a2-btn-sm">
                        إدارة كاملة
                    </a>

                    <a href="{{ route('admin.category-children.legacy-review') }}"
                       class="a2-btn a2-btn-ghost a2-btn-sm">
                        مراجعة legacy
                    </a>
                </div>
            </div>

            <div class="a2-resultsbar">
                <div class="a2-resultsbar-meta">
                    <strong>النتائج:</strong>
                    <span>{{ $childrenCount }}</span>
                </div>

                <div class="a2-resultsbar-links">
                    <a href="{{ route('admin.category-children.create', ['parent_id' => $rootIdInt]) }}"
                       class="a2-resultsbar-btn">
                        + إضافة فرعي
                    </a>
                </div>
            </div>

            <div class="a2-table-wrap">
                <table class="a2-table">
                    <thead>
                    <tr>
                        <th style="width:90px;">
                            <a class="a2-link" href="{{ $sortUrl('id') }}">ID{!! $arrow('id') !!}</a>
                        </th>
                        <th style="width:150px;">
                            <a class="a2-link" href="{{ $sortUrl('reorder') }}">Reorder{!! $arrow('reorder') !!}</a>
                        </th>
                        <th>
                            <a class="a2-link" href="{{ $sortUrl('name_ar') }}">الاسم (AR){!! $arrow('name_ar') !!}</a>
                        </th>
                        <th>
                            <a class="a2-link" href="{{ $sortUrl('name_en') }}">الاسم (EN){!! $arrow('name_en') !!}</a>
                        </th>
                        <th style="width:110px;">المجموعات</th>
                        <th style="width:120px;">الخيارات</th>
                        <th style="width:360px;">الإجراءات</th>
                    </tr>
                    </thead>

                    <tbody>
                    @forelse(($childrenSafe ?? []) as $c)
                        @php
                            $optCount = $c->relationLoaded('options') ? $c->options->count() : 0;
                        @endphp
                        <tr>
                            <td>{{ $c->id }}</td>

                            <td>
                                <div style="display:flex;gap:8px;align-items:center;">
                                    <input class="a2-input"
                                           type="number"
                                           name="child_reorders[{{ $c->id }}]"
                                           value="{{ (int) ($c->reorder ?? 0) }}"
                                           min="0"
                                           step="1"
                                           style="max-width:90px;">

                                    <button type="submit"
                                            name="save_one_id"
                                            value="{{ $c->id }}"
                                            class="a2-btn a2-btn-ghost a2-btn-sm">
                                        حفظ
                                    </button>
                                </div>
                            </td>

                            <td class="a2-fw-700">{{ $c->name_ar ?: '—' }}</td>
                            <td dir="ltr">{{ $c->name_en ?: '—' }}</td>

                            <td>
                                <span class="a2-pill a2-pill-active">{{ (int) ($c->option_groups_count ?? 0) }}</span>
                            </td>

                            <td>
                                <span class="a2-pill a2-pill-success">{{ (int) ($c->options_count ?? $optCount ?? 0) }}</span>
                            </td>

                            <td>
                                <div class="a2-actions">
                                    <a class="a2-btn a2-btn-ghost a2-btn-sm"
                                    href="{{ route('admin.category-children.edit', $c->id) }}">
                                        تعديل الفرعي
                                    </a>

                                    <a class="a2-btn a2-btn-ghost a2-btn-sm"
                                    href="{{ route('admin.category-child-option-groups.index', ['categoryChild' => $c->id, 'parent_id' => $rootIdInt]) }}">
                                        مجموعات الخيارات
                                    </a>

                                    <a class="a2-btn a2-btn-primary a2-btn-sm"
                                    href="{{ route('admin.category-child-options.edit', ['categoryChild' => $c->id, 'parent_id' => $rootIdInt]) }}">
                                        خيارات القسم الفرعي
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="a2-empty-cell">لا توجد أقسام فرعية مرتبطة</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if(!empty($childrenSafe) && method_exists($childrenSafe, 'links'))
                <div class="a2-mt-16">
                    {{ $childrenSafe->links() }}
                </div>
            @endif

            @if($childrenCount)
                <div class="a2-page-actions a2-mt-16" style="justify-content:flex-end;">
                    <button type="submit" class="a2-btn a2-btn-primary">
                        حفظ كل التعديلات
                    </button>
                </div>
            @endif
        </form>
    @else
        <div class="a2-card a2-mt-16">
            <div class="a2-card-head">
                <div>
                    <div class="a2-section-title a2-mb-0">الأقسام الرئيسية</div>
                    <div class="a2-section-subtitle">
                        اختر قسمًا رئيسيًا لعرض وإدارة الأقسام الفرعية الموحدة المرتبطة به
                    </div>
                </div>
            </div>

            <div class="a2-table-wrap">
                <table class="a2-table">
                    <thead>
                    <tr>
                        <th style="width:90px;">
                            <a class="a2-link" href="{{ $sortUrl('id') }}">ID{!! $arrow('id') !!}</a>
                        </th>
                        <th style="width:96px;">الصورة</th>
                        <th>
                            <a class="a2-link" href="{{ $sortUrl('name_ar') }}">الاسم (AR){!! $arrow('name_ar') !!}</a>
                        </th>
                        <th>
                            <a class="a2-link" href="{{ $sortUrl('name_en') }}">الاسم (EN){!! $arrow('name_en') !!}</a>
                        </th>
                        <th style="width:160px;">
                            <a class="a2-link" href="{{ $sortUrl('reorder') }}">Order{!! $arrow('reorder') !!}</a>
                        </th>
                        <th style="width:120px;">Status</th>
                        <th style="width:290px;">Actions</th>
                    </tr>
                    </thead>

                    <tbody>
                    @forelse(($roots ?? []) as $r)
                        @php
                            $isActive = (int) ($r->is_active ?? 0) === 1;
                        @endphp
                        <tr>
                            <td>{{ $r->id }}</td>

                            <td>
                                <div style="display:flex;justify-content:center;">
                                    <x-admin-v2.image :path="$r->image" size="46" radius="12px" />
                                </div>
                            </td>

                            <td class="a2-fw-700">{{ $r->name_ar ?: '—' }}</td>
                            <td dir="ltr">{{ $r->name_en ?: '—' }}</td>
                            <td>{{ (int) ($r->reorder ?? 0) }}</td>

                            <td>
                                <span class="a2-pill {{ $isActive ? 'a2-pill-active' : 'a2-pill-inactive' }}">
                                    {{ $isActive ? 'Active' : 'Inactive' }}
                                </span>
                            </td>

                            <td>
                                <div class="a2-actions">
                                    <a class="a2-btn a2-btn-ghost a2-btn-sm"
                                       href="{{ route('admin.categories.index', ['root_id' => $r->id]) }}">
                                        View Children
                                    </a>

                                    <a class="a2-btn a2-btn-ghost a2-btn-sm"
                                       href="{{ route('admin.categories.edit', ['category' => $r->id]) }}">
                                        Edit
                                    </a>

                                    <a class="a2-btn a2-btn-primary a2-btn-sm"
                                       href="{{ route('admin.category-children.create', ['parent_id' => $r->id]) }}">
                                        Add Child
                                    </a>

                                    <form method="POST"
                                          action="{{ route('admin.categories.destroy', $r->id) }}"
                                          onsubmit="return confirm('تأكيد حذف القسم الرئيسي؟');">
                                        @csrf
                                        @method('DELETE')

                                        <button type="submit" class="a2-btn a2-btn-danger a2-btn-sm">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="a2-empty-cell">لا توجد أقسام رئيسية</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection