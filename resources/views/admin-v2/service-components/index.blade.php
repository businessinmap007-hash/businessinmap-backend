@extends('admin-v2.layouts.master')

@section('title', 'Service Components')
@section('body_class', 'admin-v2 admin-v2-service-components')

@section('content')
@php
    use App\Models\ServiceOptionGroupPlacement as P;

    $surfaceLabels = [
        P::SURFACE_ITEM_FORM => 'نموذج إضافة الصنف',
        P::SURFACE_PRICING => 'شاشة التسعير',
        P::SURFACE_SEARCH_FILTER => 'فلتر بحث العميل',
        P::SURFACE_RESULT_CARD => 'كارت النتيجة',
        P::SURFACE_ITEM_DETAIL => 'تفاصيل الصنف',
        P::SURFACE_BUSINESS_PAGE => 'صفحة البزنس',
    ];
    $usageLabels = [
        P::USAGE_DEFINES_ITEM => 'تعرّف الصنف (سطر)',
        P::USAGE_CHANGES_PRICE => 'تغيّر السعر (مُعدِّل)',
        P::USAGE_DESCRIPTIVE => 'وصف فقط',
        P::USAGE_FILTER_ONLY => 'فلتر فقط',
    ];
    $inputLabels = [
        P::INPUT_SINGLE => 'اختيار واحد',
        P::INPUT_MULTIPLE => 'اختيار متعدد',
        P::INPUT_CHECKBOX => 'Checkbox',
    ];
    $name = fn ($m) => trim((string) ($m->name_ar ?? '')) ?: (trim((string) ($m->name_en ?? '')) ?: ('#' . $m->id));
    $counter = 0;
    $roleLabel = ['line' => 'سطر', 'modifier' => 'مُعدِّل', 'descriptive' => 'وصفي'];
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('مكونات الخدمة') }}</h1>
            <div class="a2-page-subtitle">
                {{ __('لكل مجموعة خيارات: أين تظهر داخل الخدمة، وكيف تُستخدم مع أي بند من بنودها. ربط الابن بالمجموعة نفسه من «خيارات التصنيفات الفرعية».') }}
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="a2-alert a2-alert-danger">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
    @endif

    <div class="a2-card a2-card--soft a2-mb-16">
        <form method="GET" action="{{ route('admin.service-components.index') }}" class="a2-filterbar">
            <div class="a2-filter-md">
                <label class="a2-label">{{ __('الخدمة') }}</label>
                <select class="a2-select" name="service_id" onchange="this.form.child_id.value=0;this.form.submit()">
                    @foreach($services as $service)
                        <option value="{{ $service->id }}" @selected($serviceId === (int) $service->id)>{{ $name($service) }} — {{ $service->key }}</option>
                    @endforeach
                </select>
            </div>
            <div class="a2-filter-md">
                <label class="a2-label">{{ __('النطاق') }}</label>
                <select class="a2-select" name="child_id" onchange="this.form.submit()">
                    <option value="0" @selected($childId === 0)>{{ __('افتراضي للخدمة كلها') }}</option>
                    @foreach($children as $child)
                        <option value="{{ $child->id }}" @selected($childId === (int) $child->id)>{{ __('استثناء لـ') }} {{ $name($child) }}</option>
                    @endforeach
                </select>
            </div>
        </form>
        @if($childId > 0)
            <div class="a2-muted a2-mt-8">{{ __('صفوف هذا الابن تتغلّب على الافتراضي لنفس (المجموعة + البند). ما لا يُعرَّف هنا يُؤخذ من الافتراضي. الصف المعطَّل يخفي المجموعة عن هذا الابن.') }}</div>
        @endif
    </div>

    <form method="POST" action="{{ route('admin.service-components.save') }}">
        @csrf
        <input type="hidden" name="service_id" value="{{ $serviceId }}">
        <input type="hidden" name="child_id" value="{{ $childId }}">

        @forelse($groups as $group)
            @php $groupRows = $rows->get($group->id, collect()); if ($groupRows->isEmpty()) { $groupRows = collect([null]); } @endphp
            <div class="a2-card a2-mb-16" data-group="{{ $group->id }}">
                <div class="a2-card-head" style="display:flex;justify-content:space-between;align-items:center">
                    <div>
                        <span class="a2-fw-900">{{ $name($group) }}</span>
                        <span class="a2-pill a2-pill-gray">{{ $roleLabel[$group->price_role] ?? $group->price_role }}</span>
                        @if($childId > 0 && $defaults->has($group->id))
                            <span class="a2-muted">{{ __('له تعريف افتراضي للخدمة') }}</span>
                        @endif
                    </div>
                    <button type="button" class="a2-btn a2-btn-ghost js-add-row" data-group="{{ $group->id }}">{{ __('+ ربط ببند آخر') }}</button>
                </div>

                <div class="a2-table-wrap">
                    <table class="a2-table">
                        <thead>
                            <tr>
                                <th>{{ __('البند') }}</th>
                                <th>{{ __('أين تظهر') }}</th>
                                <th>{{ __('كيف تُستخدم') }}</th>
                                <th>{{ __('نوع الإدخال') }}</th>
                                <th>{{ __('إلزامي') }}</th>
                                <th>{{ __('مفعّل') }}</th>
                            </tr>
                        </thead>
                        <tbody class="js-rows">
                            @foreach($groupRows as $row)
                                @php $i = $counter++; @endphp
                                @include('admin-v2.service-components._row', ['i' => $i, 'group' => $group, 'row' => $row, 'itemTypes' => $itemTypes, 'surfaceLabels' => $surfaceLabels, 'usageLabels' => $usageLabels, 'inputLabels' => $inputLabels, 'name' => $name])
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <div class="a2-card"><div class="a2-muted" style="padding:16px">{{ __('لا توجد مجموعات خيارات مرتبطة بأبناء هذه الخدمة.') }}</div></div>
        @endforelse

        @if($groups->isNotEmpty())
            <button class="a2-btn a2-btn-primary" type="submit">{{ __('حفظ') }}</button>
        @endif
    </form>

    <template id="row-template">
        @include('admin-v2.service-components._row', ['i' => '__I__', 'group' => (object) ['id' => '__G__'], 'row' => null, 'itemTypes' => $itemTypes, 'surfaceLabels' => $surfaceLabels, 'usageLabels' => $usageLabels, 'inputLabels' => $inputLabels, 'name' => $name])
    </template>
</div>

<script>
(function () {
    var next = {{ $counter }};
    var tpl = document.getElementById('row-template');
    document.querySelectorAll('.js-add-row').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var group = btn.getAttribute('data-group');
            var html = tpl.innerHTML.split('__I__').join(next++).split('__G__').join(group);
            btn.closest('.a2-card').querySelector('.js-rows').insertAdjacentHTML('beforeend', html);
        });
    });
})();
</script>
@endsection
