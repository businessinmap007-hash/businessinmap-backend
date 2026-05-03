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

    $rootsSafe = collect($roots ?? []);
    $childrenSafe = $children ?? collect();
    $servicesSafe = collect($services ?? $platformServices ?? []);

    $nameOf = function ($item) {
        $ar = (string) ($item->name_ar ?? '');
        $en = (string) ($item->name_en ?? '');
        return $ar !== '' ? $ar : ($en !== '' ? $en : '—');
    };

    $childrenCount = method_exists($childrenSafe, 'total')
        ? $childrenSafe->total()
        : collect($childrenSafe)->count();

    $sortUrl = function (string $col) use ($rootIdInt, $qVal, $activeVal, $perPageVal, $sortNow, $dirNow) {
        $nextDir = ($sortNow === $col && $dirNow === 'asc') ? 'desc' : 'asc';

        return route('admin.categories.index', [
            'root_id' => $rootIdInt,
            'q' => $qVal,
            'active' => $activeVal,
            'per_page' => $perPageVal,
            'sort' => $col,
            'dir' => $nextDir,
        ]);
    };

    $arrow = function (string $col) use ($sortNow, $dirNow) {
        return $sortNow === $col ? ($dirNow === 'asc' ? ' ▲' : ' ▼') : '';
    };
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">الأقسام</h1>
            <div class="a2-page-subtitle">
                إدارة الأقسام الرئيسية، الفروع الموحدة، وربط الخدمات والرسوم
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.categories.create') }}" class="a2-btn a2-btn-primary">
                + إضافة قسم رئيسي
            </a>

            <a href="{{ route('admin.category-children.index') }}" class="a2-btn a2-btn-ghost">
                إدارة كل الأقسام الفرعية
            </a>

            @if($rootIdInt > 0)
                <a href="{{ route('admin.category-children.create', ['parent_id' => $rootIdInt]) }}"
                   class="a2-btn a2-btn-primary">
                    + إضافة قسم فرعي
                </a>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="a2-alert a2-alert-danger">{{ session('error') }}</div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="a2-stat-grid a2-mb-16">
        <div class="a2-stat-card">
            <div class="a2-stat-label">الأقسام الرئيسية</div>
            <div class="a2-stat-value">{{ $rootsSafe->count() }}</div>
            <div class="a2-stat-note">Root Categories</div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">القسم المحدد</div>
            <div class="a2-stat-value">{{ $rootIdInt > 0 ? '#'.$rootIdInt : '—' }}</div>
            <div class="a2-stat-note">
                {{ $rootIdInt > 0 && !empty($root) ? $nameOf($root) : 'عرض الأقسام الرئيسية' }}
            </div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">الفروع المعروضة</div>
            <div class="a2-stat-value">{{ $rootIdInt > 0 ? $childrenCount : 0 }}</div>
            <div class="a2-stat-note">Category Children</div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">الخدمات المتاحة</div>
            <div class="a2-stat-value">{{ $servicesSafe->count() }}</div>
            <div class="a2-stat-note">Platform Services</div>
        </div>
    </div>

    <div class="a2-card a2-mb-16">
        <form method="GET" action="{{ route('admin.categories.index') }}" class="a2-filterbar">
            <input class="a2-input a2-filter-search"
                   type="text"
                   name="q"
                   value="{{ $qVal }}"
                   placeholder="{{ $rootIdInt > 0 ? 'بحث داخل الفروع' : 'بحث داخل الأقسام الرئيسية' }}">

            <select class="a2-select a2-filter-md" name="root_id">
                <option value="0" @selected($rootIdInt === 0)>كل الأقسام الرئيسية</option>
                @foreach($rootsSafe as $r)
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
                @foreach(($activeOptions ?? ['' => 'الكل', '1' => 'Active', '0' => 'Inactive']) as $k => $label)
                    <option value="{{ $k }}" @selected((string) $activeVal === (string) $k)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-sm" name="per_page">
                @foreach(($perPageOptions ?? [20, 50, 100]) as $n)
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
        <div class="a2-card a2-card--section a2-mb-16">
            <div class="a2-card-head">
                <div>
                    <div class="a2-section-title a2-mb-0">القسم الرئيسي المختار</div>
                    <div class="a2-section-subtitle">{{ $nameOf($root) }} #{{ $root->id }}</div>
                </div>

                <div class="a2-page-actions">
                    <a class="a2-btn a2-btn-ghost"
                       href="{{ route('admin.categories.edit', ['category' => $root->id]) }}">
                        تعديل القسم
                    </a>

                    <a class="a2-btn a2-btn-primary"
                       href="{{ route('admin.category-children.create', ['parent_id' => $root->id]) }}">
                        + إضافة فرع
                    </a>
                </div>
            </div>

            <div style="display:flex;gap:14px;align-items:center;">
                <x-admin-v2.image :path="$root->image" size="68" radius="16px" />
                <div>
                    <div class="a2-fw-900" style="font-size:18px;">
                        {{ $root->name_ar ?: ($root->name_en ?: '—') }}
                    </div>
                    @if(!empty($root->name_en))
                        <div class="a2-muted">{{ $root->name_en }}</div>
                    @endif
                </div>
            </div>
        </div>

        <form method="POST"
              action="{{ route('admin.categories.services-bulk.apply') }}"
              class="a2-card a2-card--section a2-mb-16">
            @csrf

            <input type="hidden" name="root_id" value="{{ $rootIdInt }}">

            <div class="a2-card-head">
                <div>
                    <div class="a2-section-title a2-mb-0">Bulk Services + Fees</div>
                    <div class="a2-section-subtitle">
                        تطبيق الخدمات والرسوم على الفروع المحددة دفعة واحدة
                    </div>
                </div>
            </div>

            @if($childrenCount > 0)
                <div class="a2-form-grid">
                    <div>
                        <label class="a2-label">الأقسام الفرعية</label>
                        <select name="category_ids[]" multiple class="a2-select" required style="min-height:140px;">
                            @foreach($childrenSafe as $child)
                                <option value="{{ $child->id }}">
                                    #{{ $child->id }} - {{ $child->name_ar ?: ($child->name_en ?: '—') }}
                                </option>
                            @endforeach
                        </select>
                        <div class="a2-muted a2-mt-8">يمكن اختيار أكثر من فرع.</div>
                    </div>

                    <div>
                        <label class="a2-label">الخدمات</label>
                        <select name="platform_service_ids[]" multiple class="a2-select" required style="min-height:140px;">
                            @foreach($servicesSafe as $service)
                                <option value="{{ $service->id }}">
                                    #{{ $service->id }} - {{ $service->name_ar ?: ($service->name_en ?: $service->key) }}
                                </option>
                            @endforeach
                        </select>
                        <div class="a2-muted a2-mt-8">الخدمات المختارة سيتم ربطها بالفروع.</div>
                    </div>
                </div>

                <div class="a2-divider"></div>

                <div class="a2-form-grid">
                    <div>
                        <label class="a2-label">طريقة التطبيق</label>
                        <select name="mode" class="a2-select" required>
                            <option value="append">إضافة / تحديث</option>
                            <option value="replace">استبدال كامل</option>
                            <option value="remove">حذف الربط</option>
                        </select>
                    </div>

                    <div>
                        <label class="a2-label">العملة</label>
                        <input type="text" name="currency" class="a2-input" value="EGP">
                    </div>
                </div>

                <div class="a2-form-grid a2-mt-12">
                    <div class="a2-card a2-card--soft" style="padding:14px;">
                        <label class="a2-label">رسوم البزنس</label>

                        <label style="display:flex;gap:8px;align-items:center;margin-bottom:10px;">
                            <input type="checkbox" name="business_fee_enabled" value="1">
                            <span>تفعيل رسوم البزنس</span>
                        </label>

                        <input type="number"
                               step="0.01"
                               min="0"
                               name="business_fee_amount"
                               class="a2-input"
                               placeholder="مثال: 5">
                    </div>

                    <div class="a2-card a2-card--soft" style="padding:14px;">
                        <label class="a2-label">رسوم العميل</label>

                        <label style="display:flex;gap:8px;align-items:center;margin-bottom:10px;">
                            <input type="checkbox" name="client_fee_enabled" value="1">
                            <span>تفعيل رسوم العميل</span>
                        </label>

                        <input type="number"
                               step="0.01"
                               min="0"
                               name="client_fee_amount"
                               class="a2-input"
                               placeholder="مثال: 3">
                    </div>
                </div>

                <div class="a2-mt-12">
                    <label class="a2-label">ملاحظات الرسوم</label>
                    <input type="text"
                           name="fee_notes"
                           class="a2-input"
                           placeholder="اختياري">
                </div>

                <div class="a2-page-actions a2-mt-16" style="justify-content:flex-end;">
                    <button type="submit" class="a2-btn a2-btn-primary">
                        تطبيق الخدمات والرسوم
                    </button>
                </div>
            @else
                <div class="a2-alert a2-alert-warning">
                    لا توجد فروع لهذا القسم الرئيسي حتى الآن.
                </div>
            @endif
        </form>
    @endif

    <div class="a2-card a2-mt-16">
        <div class="a2-card-head">
            <div>
                <div class="a2-section-title a2-mb-0">
                    {{ $rootIdInt > 0 ? 'الأقسام الفرعية' : 'الأقسام الرئيسية' }}
                </div>
                <div class="a2-section-subtitle">
                    {{ $rootIdInt > 0 ? 'الفروع المرتبطة بالقسم المحدد' : 'اختر قسمًا رئيسيًا لعرض فروعه' }}
                </div>
            </div>
        </div>

        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th>
                            <a href="{{ $sortUrl('id') }}">ID{!! $arrow('id') !!}</a>
                        </th>
                        <th>الصورة</th>
                        <th>
                            <a href="{{ $sortUrl('name_ar') }}">الاسم العربي{!! $arrow('name_ar') !!}</a>
                        </th>
                        <th>
                            <a href="{{ $sortUrl('name_en') }}">الاسم الإنجليزي{!! $arrow('name_en') !!}</a>
                        </th>
                        <th>
                            <a href="{{ $sortUrl('reorder') }}">الترتيب{!! $arrow('reorder') !!}</a>
                        </th>
                        <th>الحالة</th>
                        <th style="width:260px;">إجراءات</th>
                    </tr>
                </thead>

                <tbody>
                    @if($rootIdInt > 0)
                        @forelse($childrenSafe as $child)
                            <tr>
                                <td>#{{ $child->id }}</td>
                                <td>{{ $child->name_ar ?: '—' }}</td>
                                <td>{{ $child->name_en ?: '—' }}</td>
                                <td>{{ $child->reorder ?? 0 }}</td>
                                <td>
                                    <span class="a2-badge a2-badge-success">Active</span>
                                </td>
                                <td>
                                    <div class="a2-row-actions">
                                        <a class="a2-btn a2-btn-sm a2-btn-ghost"
                                           href="{{ route('admin.category-children.edit', $child->id) }}">
                                            تعديل
                                        </a>

                                        @if(Route::has('admin.category-child-options.edit'))
                                            <a class="a2-btn a2-btn-sm a2-btn-ghost"
                                               href="{{ route('admin.category-child-options.edit', $child->id) }}">
                                                Options
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="a2-empty">لا توجد أقسام فرعية.</div>
                                </td>
                            </tr>
                        @endforelse
                    @else
                        @forelse($rootsSafe as $cat)
                            <tr>
                                <td>#{{ $cat->id }}</td>
                                <td><x-admin-v2.image :path="$cat->image" size="48" radius="12px" /></td>
                                <td>{{ $cat->name_ar ?: '—' }}</td>
                                <td>{{ $cat->name_en ?: '—' }}</td>
                                <td>{{ $cat->reorder ?? 0 }}</td>
                                <td>
                                    @if((bool)($cat->is_active ?? true))
                                        <span class="a2-badge a2-badge-success">Active</span>
                                    @else
                                        <span class="a2-badge a2-badge-muted">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="a2-row-actions">
                                        <a class="a2-btn a2-btn-sm a2-btn-primary"
                                           href="{{ route('admin.categories.index', ['root_id' => $cat->id]) }}">
                                            الفروع
                                        </a>

                                        <a class="a2-btn a2-btn-sm a2-btn-ghost"
                                           href="{{ route('admin.categories.edit', $cat->id) }}">
                                            تعديل
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="a2-empty">لا توجد أقسام رئيسية.</div>
                                </td>
                            </tr>
                        @endforelse
                    @endif
                </tbody>
            </table>
        </div>

        @if($rootIdInt > 0 && method_exists($childrenSafe, 'links'))
            <div class="a2-mt-16">
                {{ $childrenSafe->links() }}
            </div>
        @endif
    </div>
</div>
@endsection