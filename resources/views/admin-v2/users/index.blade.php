@extends('admin-v2.layouts.master')

@section('title', 'المستخدمون')
@section('body_class', 'admin-v2-users')

@section('content')
@php
    use Illuminate\Support\Str;

    $qVal = (string) ($q ?? '');
    $typeVal = (string) ($type ?? '');
    $activeVal = (string) ($active ?? '');
    $subActiveVal = (string) ($subActive ?? '');
    $trashedVal = (string) ($trashed ?? '');
    $perPageVal = (int) ($perPage ?? 50);
    $sortNow = (string) ($sort ?? 'id');
    $dirNow = (string) ($dir ?? 'desc');

    $categoryIdVal = (int) ($categoryId ?? 0);
    $categoryChildIdVal = (int) ($categoryChildId ?? 0);

    $optionIdsVal = collect($optionIds ?? [])
        ->map(fn ($id) => (int) $id)
        ->filter(fn ($id) => $id > 0)
        ->values()
        ->all();

    $perPageOptions = $perPageOptions ?? [10, 20, 50, 100];

    $sortOptions = [
        'id' => 'ID',
        'name' => 'Name',
        'phone' => 'Phone',
        'email' => 'Email',
        'type' => 'Type',
        'activated_at' => 'Activated',
    ];

    $limit15 = fn ($v) => Str::limit((string) $v, 15, '...');
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">المستخدمون</h1>
            <div class="a2-page-subtitle">إدارة حسابات admin / client / business</div>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="a2-card">
        <form method="GET" action="{{ route('admin.users.index') }}" class="a2-filterbar" id="usersFilterForm">
            <input
                class="a2-input a2-filter-search"
                name="q"
                value="{{ $qVal }}"
                placeholder="بحث بالاسم / الهاتف / البريد"
            >

            <select class="a2-select a2-filter-sm" name="type">
                @foreach(($types ?? []) as $k => $label)
                    <option value="{{ $k }}" @selected($typeVal === (string) $k)>{{ $label }}</option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-md" name="category_id" id="filterCategory">
                <option value="">كل التصنيفات</option>
                @foreach(($categories ?? []) as $cat)
                    <option value="{{ $cat->id }}" @selected($categoryIdVal === (int) $cat->id)>
                        {{ $cat->name_ar ?: $cat->name_en ?: ('#' . $cat->id) }}
                    </option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-md" name="category_child_id" id="filterChild">
                <option value="">كل الأقسام الفرعية</option>
                @foreach(($children ?? []) as $child)
                    <option value="{{ $child->id }}" @selected($categoryChildIdVal === (int) $child->id)>
                        {{ $child->name_ar ?: $child->name_en ?: ('#' . $child->id) }}
                    </option>
                @endforeach
            </select>

            <select
                class="a2-select a2-filter-md"
                name="option_ids[]"
                id="filterOption"
                multiple
            >
                @foreach(($options ?? []) as $opt)
                    <option value="{{ $opt->id }}" @selected(in_array((int) $opt->id, $optionIdsVal, true))>
                        {{ $opt->name_ar ?: $opt->name_en ?: ('#' . $opt->id) }}
                    </option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-md" name="trashed">
                @foreach(($trashedOptions ?? []) as $k => $label)
                    <option value="{{ $k }}" @selected($trashedVal === (string) $k)>{{ $label }}</option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-sm" name="sort">
                @foreach($sortOptions as $k => $label)
                    <option value="{{ $k }}" @selected($sortNow === $k)>{{ $label }}</option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-sm" name="dir">
                <option value="desc" @selected($dirNow === 'desc')>DESC</option>
                <option value="asc" @selected($dirNow === 'asc')>ASC</option>
            </select>

            <select class="a2-select a2-filter-sm" name="per_page">
                @foreach($perPageOptions as $n)
                    <option value="{{ $n }}" @selected((int) $perPageVal === (int) $n)>
                        {{ $n }} / صفحة
                    </option>
                @endforeach
            </select>

            <div class="a2-filter-actions">
                <button type="submit" class="a2-btn a2-btn-primary">تطبيق</button>
                <a class="a2-btn a2-btn-ghost" href="{{ route('admin.users.index') }}">تفريغ</a>
            </div>
        </form>

        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>الصورة</th>
                        <th>الاسم</th>
                        <th>النوع</th>
                        <th>التصنيف</th>
                        <th>القسم الفرعي</th>
                        <th>الهاتف</th>
                        <th>الاشتراك</th>
                        <th>التفعيل</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($items as $row)
                        @php
                            $showUrl = route('admin.users.show', $row->id);

                            $name = (string) ($row->name ?? '');
                            $userLabel = $name !== '' ? $name : ('#' . $row->id);

                            $categoryName = $row->category?->name_ar ?: ($row->category?->name_en ?: '—');
                            $childName = $row->categoryChild?->name_ar ?: ($row->categoryChild?->name_en ?: '—');

                            $sub = $row->latestSubscription;
                            $imgPath = $row->logo ?? null;
                        @endphp

                        <tr class="{{ method_exists($row, 'trashed') && $row->trashed() ? 'a2-row-trashed' : '' }}">
                            <td>
                                <a class="a2-link" href="{{ $showUrl }}">{{ $row->id }}</a>
                            </td>

                            <td>
                                @if($imgPath)
                                    <x-admin-v2.image :path="$imgPath" size="52" radius="12px" />
                                @else
                                    <div class="a2-album-cover-empty">—</div>
                                @endif
                            </td>

                            <td>
                                <a class="a2-link a2-clip" href="{{ $showUrl }}" title="{{ $userLabel }}">
                                    {{ $limit15($userLabel) }}
                                </a>
                            </td>

                            <td>{{ $row->type ?: '—' }}</td>

                            <td title="{{ $categoryName }}">
                                {{ $categoryName }}
                            </td>

                            <td title="{{ $childName }}">
                                {{ $childName }}
                            </td>

                            <td dir="ltr">{{ $row->phone ?: '—' }}</td>

                            <td>
                                @if($sub && (int) ($sub->is_active ?? 0) === 1)
                                    <span class="a2-pill a2-pill-sub-active">نشط</span>
                                @else
                                    <span class="a2-pill a2-pill-sub-none">—</span>
                                @endif
                            </td>

                            <td>
                                @if($row->activated_at)
                                    <span class="a2-pill a2-pill-success">مفعل</span>
                                @else
                                    <span class="a2-pill a2-pill-danger">غير مفعل</span>
                                @endif
                            </td>

                            <td>
                                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                    <a class="a2-btn a2-btn-ghost a2-btn-sm" href="{{ route('admin.users.edit', $row->id) }}">
                                        تعديل
                                    </a>

                                    <form method="POST" action="{{ route('admin.users.destroy', $row->id) }}" onsubmit="return confirm('حذف المستخدم؟');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="a2-btn a2-btn-ghost a2-btn-sm" type="submit">حذف</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="a2-empty-cell">لا يوجد بيانات</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(method_exists($items, 'links'))
            <div class="a2-paginate">
                {{ $items->links() }}
            </div>
        @endif
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const childCatalog = @json($childCatalog ?? (object) []);
    const optionCatalog = @json($optionCatalog ?? (object) []);

    const categorySelect = document.getElementById('filterCategory');
    const childSelect = document.getElementById('filterChild');
    const optionSelect = document.getElementById('filterOption');

    const selectedChildId = {{ (int) ($categoryChildId ?? 0) }};
    const selectedOptionIds = @json(array_map('intval', $optionIdsVal ?? []));

    function itemLabel(item) {
        return item && item.name_ar && item.name_ar !== ''
            ? item.name_ar
            : ((item && item.name_en) ? item.name_en : ((item && item.id) ? ('#' + item.id) : '—'));
    }

    function destroyTom(el) {
        if (el && el.tomselect) {
            el.tomselect.destroy();
        }
    }

    function initOptionSelect() {
        if (!optionSelect || typeof TomSelect === 'undefined') return;

        destroyTom(optionSelect);

        new TomSelect(optionSelect, {
            plugins: ['remove_button'],
            create: false,
            persist: false,
            maxOptions: null,
            hideSelected: true,
            closeAfterSelect: false,
            placeholder: 'اختر خيارًا أو أكثر',
        });
    }

    function refillChildren(categoryId, keepChildId = 0) {
        const rows = childCatalog[String(categoryId)] || childCatalog[categoryId] || [];

        childSelect.innerHTML = '<option value="">كل الأقسام الفرعية</option>';

        rows.forEach(function (child) {
            const opt = document.createElement('option');
            opt.value = child.id;
            opt.textContent = itemLabel(child);

            if (parseInt(keepChildId, 10) === parseInt(child.id, 10)) {
                opt.selected = true;
            }

            childSelect.appendChild(opt);
        });
    }

    function normalizeOptions(source) {
        if (Array.isArray(source)) {
            return source;
        }

        if (!source || typeof source !== 'object') {
            return [];
        }

        const grouped = Array.isArray(source.groups)
            ? source.groups.flatMap(group => Array.isArray(group.options) ? group.options : [])
            : [];

        const ungrouped = Array.isArray(source.ungrouped)
            ? source.ungrouped
            : [];

        const merged = [...grouped, ...ungrouped];
        const seen = new Set();

        return merged.filter(item => {
            const id = parseInt(item.id || 0, 10);
            if (!id || seen.has(id)) return false;
            seen.add(id);
            return true;
        });
    }

    function refillOptions(childId, keepOptionIds = []) {
        const raw = optionCatalog[String(childId)] || optionCatalog[childId] || [];
        const rows = normalizeOptions(raw);

        destroyTom(optionSelect);
        optionSelect.innerHTML = '';

        rows.forEach(function (item) {
            const opt = document.createElement('option');
            opt.value = item.id;
            opt.textContent = itemLabel(item);

            if (Array.isArray(keepOptionIds) && keepOptionIds.includes(parseInt(item.id, 10))) {
                opt.selected = true;
            }

            optionSelect.appendChild(opt);
        });

        initOptionSelect();
    }

    categorySelect.addEventListener('change', function () {
        const categoryId = parseInt(this.value || 0, 10);

        refillChildren(categoryId, 0);

        destroyTom(optionSelect);
        optionSelect.innerHTML = '';
        initOptionSelect();
    });

    childSelect.addEventListener('change', function () {
        const childId = parseInt(this.value || 0, 10);

        if (childId > 0) {
            refillOptions(childId, []);
        } else {
            destroyTom(optionSelect);
            optionSelect.innerHTML = '';
            initOptionSelect();
        }
    });

    if (parseInt(categorySelect.value || 0, 10) > 0) {
        refillChildren(parseInt(categorySelect.value, 10), selectedChildId);
    }

    if (selectedChildId > 0) {
        refillOptions(selectedChildId, selectedOptionIds);
    } else {
        initOptionSelect();
    }
});
</script>
@endsection