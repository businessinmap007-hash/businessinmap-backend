@extends('admin-v2.layouts.master')

@section('title', 'تعديل القسم الفرعي')
@section('body_class', 'admin-v2-category-children-edit')

@section('content')
@php
    $parentIdInt = (int) ($parentId ?? 0);

    $childName = $categoryChild->name_ar
        ?: ($categoryChild->name_en ?: ('#' . $categoryChild->id));

    $rootName = !empty($root)
        ? ($root->name_ar ?: ($root->name_en ?: ('#' . $root->id)))
        : null;

    $selectedParentIds = collect($selectedParentIds ?? [])->map(fn ($id) => (int) $id)->all();
    $selectedServiceIds = collect($selectedServiceIds ?? [])->map(fn ($id) => (int) $id)->all();
    $selectedOptionIds = collect($selectedOptionIds ?? [])->map(fn ($id) => (int) $id)->all();

    $optionCount = (int) ($categoryChild->options_count ?? (($categoryChild->options ?? collect())->count()));
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">تعديل القسم الفرعي</h1>
            <div class="a2-page-subtitle">
                <div><strong>القسم الفرعي:</strong> {{ $childName }}</div>

                @if($rootName)
                    <div class="a2-mt-8"><strong>القسم الرئيسي:</strong> {{ $rootName }}</div>
                @endif
            </div>
        </div>

        <div class="a2-page-actions">
            @if($parentIdInt > 0)
                <a
                    href="{{ route('admin.categories.index', ['root_id' => $parentIdInt]) }}"
                    class="a2-btn a2-btn-ghost"
                >
                    رجوع إلى الأقسام
                </a>
            @endif

            <a
                href="{{ route('admin.category-child-options.edit', ['categoryChild' => $categoryChild->id]) }}"
                class="a2-btn a2-btn-ghost"
            >
                إدارة الخيارات
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
            <ul style="margin:0;padding-inline-start:18px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form
        method="POST"
        action="{{ route('admin.category-children.update', ['categoryChild' => $categoryChild->id]) }}"
        class="a2-card"
    >
        @csrf
        @method('PUT')

        <input type="hidden" name="return_to" value="categories.index">

        <div class="a2-form-grid">
            <div class="a2-form-group">
                <label class="a2-label">الاسم العربي</label>
                <input
                    type="text"
                    name="name_ar"
                    class="a2-input"
                    value="{{ old('name_ar', $categoryChild->name_ar) }}"
                    required
                >
            </div>

            <div class="a2-form-group">
                <label class="a2-label">الاسم الإنجليزي</label>
                <input
                    type="text"
                    name="name_en"
                    class="a2-input"
                    value="{{ old('name_en', $categoryChild->name_en) }}"
                >
            </div>

            <div class="a2-form-group">
                <label class="a2-label">الترتيب</label>
                <input
                    type="number"
                    min="0"
                    name="reorder"
                    class="a2-input"
                    value="{{ old('reorder', (int) ($categoryChild->reorder ?? 0)) }}"
                >
            </div>
        </div>

        <hr class="a2-divider">

        <div class="a2-section-title">الأقسام الرئيسية المرتبط بها</div>
        <div class="a2-section-subtitle">
            يمكن ربط القسم الفرعي بأكثر من قسم رئيسي
        </div>

        <div class="a2-page-actions a2-mt-12" style="justify-content:flex-start;">
            @foreach(($parents ?? collect()) as $parent)
                @php
                    $parentName = $parent->name_ar ?: ($parent->name_en ?: ('#' . $parent->id));
                    $isChecked = in_array((int) $parent->id, old('parent_ids', $selectedParentIds), true);
                @endphp

                <label class="a2-check" style="padding:8px 12px;border:1px solid var(--a2-border);border-radius:12px;background:#fff;">
                    <input
                        type="checkbox"
                        name="parent_ids[]"
                        value="{{ $parent->id }}"
                        @checked($isChecked)
                    >
                    <span>{{ $parentName }}</span>
                </label>
            @endforeach
        </div>

        <hr class="a2-divider">

        <div class="a2-section-title">الخدمات المتاحة لهذا القسم الفرعي</div>
        <div class="a2-section-subtitle">
            اختر خدمة واحدة أو أكثر
        </div>

        <div class="a2-page-actions a2-mt-12" style="justify-content:flex-start;">
            @foreach(($services ?? collect()) as $service)
                @php
                    $serviceName = $service->name_ar ?: ($service->name_en ?: ($service->key ?: ('#' . $service->id)));
                    $isChecked = in_array((int) $service->id, old('service_ids', $selectedServiceIds), true);
                @endphp

                <label class="a2-check" style="padding:8px 12px;border:1px solid var(--a2-border);border-radius:12px;background:#fff;">
                    <input
                        type="checkbox"
                        name="service_ids[]"
                        value="{{ $service->id }}"
                        @checked($isChecked)
                    >
                    <span>
                        {{ $serviceName }}
                        @if(!empty($service->key))
                            <span class="a2-muted" dir="ltr">({{ $service->key }})</span>
                        @endif
                    </span>
                </label>
            @endforeach
        </div>

        <hr class="a2-divider">

        <div class="a2-card a2-card--soft">
            <div class="a2-card-head">
                <div>
                    <div class="a2-section-title a2-mb-0">الخيارات المختارة لهذا القسم الفرعي</div>
                    <div class="a2-section-subtitle">
                        العدد الحالي:
                        <strong>{{ $optionCount }}</strong>
                    </div>
                </div>

                <div class="a2-page-actions">
                    <a
                        href="{{ route('admin.category-child-options.edit', ['categoryChild' => $categoryChild->id]) }}"
                        class="a2-btn a2-btn-ghost"
                    >
                        إدارة الخيارات
                    </a>
                </div>
            </div>

            @if(($categoryChild->options ?? collect())->isEmpty())
                <div class="a2-empty-cell">
                    لا توجد خيارات مختارة لهذا القسم الفرعي حتى الآن.
                </div>
            @else
                <div class="a2-page-actions a2-mt-12" style="justify-content:flex-start;">
                    @foreach($categoryChild->options as $opt)
                        @php
                            $optName = $opt->name_ar ?: ($opt->name_en ?: ('#' . $opt->id));
                        @endphp

                        <span class="a2-pill a2-pill-gray">
                            {{ $optName }}
                            <span class="a2-muted">#{{ $opt->id }}</span>
                        </span>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="a2-page-actions a2-mt-16">
            <button type="submit" class="a2-btn a2-btn-primary">
                حفظ التعديلات
            </button>

            @if($parentIdInt > 0)
                <a
                    href="{{ route('admin.categories.index', ['root_id' => $parentIdInt]) }}"
                    class="a2-btn a2-btn-ghost"
                >
                    رجوع
                </a>
            @else
                <a
                    href="{{ route('admin.category-children.index') }}"
                    class="a2-btn a2-btn-ghost"
                >
                    رجوع
                </a>
            @endif
        </div>
    </form>
</div>
@endsection