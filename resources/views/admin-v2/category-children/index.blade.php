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
            'dir'  => $nextDir,
        ]));
    };

    $arrow = function (string $col) use ($sortNow, $dirNow) {
        if ($sortNow !== $col) {
            return '';
        }

        return $dirNow === 'asc' ? ' ▲' : ' ▼';
    };

    $parentName = function ($cat) {
        $ar = (string) ($cat->name_ar ?? '');
        $en = (string) ($cat->name_en ?? '');

        return $ar !== '' ? $ar : ($en !== '' ? $en : '—');
    };
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">الأقسام الفرعية الموحّدة</h1>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.category-children.create', $parentIdInt > 0 ? ['parent_id' => $parentIdInt] : []) }}"
               class="a2-btn a2-btn-primary">
                + إضافة قسم فرعي موحّد
            </a>

            <a href="{{ route('admin.categories.index', $parentIdInt > 0 ? ['root_id' => $parentIdInt] : []) }}"
               class="a2-btn a2-btn-ghost">
                رجوع إلى الأقسام الرئيسية
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

    <div class="a2-card">
        <form method="GET" action="{{ route('admin.category-children.index') }}" class="a2-filterbar">
            <input class="a2-input a2-filter-search"
                   type="text"
                   name="q"
                   value="{{ $qVal }}"
                   placeholder="بحث داخل الأقسام الفرعية">

            <select class="a2-select a2-filter-md" name="parent_id">
                <option value="0" @selected($parentIdInt === 0)>كل الأقسام الرئيسية</option>
                @foreach(($parents ?? []) as $p)
                    <option value="{{ $p->id }}" @selected($parentIdInt === (int) $p->id)>
                        #{{ $p->id }} - {{ $parentName($p) }}
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
                    <option value="{{ $n }}" @selected((int) $perPageVal === (int) $n)>
                        {{ $n }} / صفحة
                    </option>
                @endforeach
            </select>

            <div class="a2-filter-actions">
                <button type="submit" class="a2-btn a2-btn-primary">تطبيق</button>

                <a class="a2-btn a2-btn-ghost"
                   href="{{ route('admin.category-children.index', $parentIdInt > 0 ? ['parent_id' => $parentIdInt] : []) }}">
                    تفريغ
                </a>
            </div>
        </form>
    </div>

    @if($parentIdInt > 0 && !empty($parent))
        <div class="a2-card a2-card--section">
            <div class="a2-card-head">
                <div>
                    <div class="a2-section-title a2-mb-0">القسم الرئيسي المختار</div>
                </div>

                <div class="a2-page-actions">
                    <a class="a2-btn a2-btn-ghost"
                       href="{{ route('admin.categories.edit', ['category' => $parent->id]) }}">
                        تعديل القسم الرئيسي
                    </a>
                </div>
            </div>

            <div style="font-weight:900;font-size:18px;">
                {{ $parent->name_ar ?: ($parent->name_en ?: '—') }}
                <span class="a2-muted">#{{ $parent->id }}</span>
            </div>

            @if(!empty($parent->name_en))
                <div class="a2-page-subtitle">
                    EN: {{ $parent->name_en }}
                </div>
            @endif
        </div>
    @endif

    <div class="a2-card">
        <div class="a2-card-head">
            <div>
                <div class="a2-section-title a2-mb-0">قائمة الأقسام الفرعية</div>
                <div class="a2-section-subtitle">
                    @if(isset($rows) && method_exists($rows, 'total'))
                        إجمالي النتائج: {{ $rows->total() }}
                    @else
                        عرض الأقسام الفرعية
                    @endif
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

                    <th style="width:120px;">
                        <a class="a2-link" href="{{ $sortUrl('reorder') }}">Reorder{!! $arrow('reorder') !!}</a>
                    </th>

                    <th>
                        <a class="a2-link" href="{{ $sortUrl('name_ar') }}">الاسم (AR){!! $arrow('name_ar') !!}</a>
                    </th>

                    <th>
                        <a class="a2-link" href="{{ $sortUrl('name_en') }}">الاسم (EN){!! $arrow('name_en') !!}</a>
                    </th>

                    <th style="width:120px;">Parents</th>
                    <th>الأقسام الرئيسية المرتبطة</th>
                    <th style="width:110px;">المجموعات</th>
                    <th style="width:120px;">Options</th>
                    <th style="width:220px;">Actions</th>
                </tr>
                </thead>

                <tbody>
                @forelse($rows as $row)
                 @php
                $optionsCount = $row->relationLoaded('options') ? $row->options->count() : 0;
            @endphp
                    <tr>
                        <td>{{ $row->id }}</td>
                        <td>{{ (int) ($row->reorder ?? 0) }}</td>
                        <td class="a2-fw-700">{{ $row->name_ar ?: '—' }}</td>
                        <td dir="ltr">{{ $row->name_en ?: '—' }}</td>
                        <td>{{ (int) ($row->parents_count ?? 0) }}</td>

                        <td>
                            @if($row->parents->count())
                                <div class="a2-option-chip-grid" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr));">
                                    @foreach($row->parents as $p)
                                        <div class="a2-option-chip-card" style="padding:10px;">
                                            <div class="a2-option-chip-title">
                                                {{ $p->name_ar ?: ($p->name_en ?: '—') }}
                                            </div>
                                            <div class="a2-option-chip-sub" dir="ltr">
                                                #{{ $p->id }}
                                                @if(!empty($p->name_en))
                                                    — {{ $p->name_en }}
                                                @endif
                                            </div>

                                            <form method="POST"
                                                  action="{{ route('admin.category-children.detach-parent', ['categoryChild' => $row->id, 'parent' => $p->id]) }}"
                                                  onsubmit="return confirm('تأكيد فصل الربط؟');"
                                                  style="margin-top:8px;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="a2-btn a2-btn-danger a2-btn-sm">
                                                    فصل
                                                </button>
                                            </form>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <span class="a2-muted">لا يوجد ربط</span>
                            @endif
                        </td>
                        <td>
                            <span class="a2-pill a2-pill-active">{{ (int) ($row->option_groups_count ?? $c->option_groups_count ?? 0) }}</span>
                        </td>

                        <td>
                            <span class="a2-pill a2-pill-success">{{ $optionsCount }}</span>
                        </td>

                        <td>
                            <div class="a2-actions">
                                <a class="a2-btn a2-btn-ghost a2-btn-sm"
                                   href="{{ route('admin.category-children.edit', $row->id) }}">
                                    Edit
                                </a>

                                <form method="POST"
                                      action="{{ route('admin.category-children.destroy', $row->id) }}"
                                      onsubmit="return confirm('تأكيد حذف القسم الفرعي الموحّد؟');">
                                    @csrf
                                    @method('DELETE')

                                    @if($parentIdInt > 0)
                                        <input type="hidden" name="parent_id" value="{{ $parentIdInt }}">
                                    @endif

                                    <button type="submit" class="a2-btn a2-btn-danger a2-btn-sm">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="a2-empty-cell">لا توجد أقسام فرعية موحّدة</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if(isset($rows) && method_exists($rows, 'links'))
            <div class="a2-mt-16">
                {{ $rows->links() }}
            </div>
        @endif
    </div>
</div>
@endsection