@extends('admin-v2.layouts.master')

@section('title', 'Service Components')
@section('body_class', 'admin-v2 admin-v2-service-components')

@section('content')
@php
    use App\Models\ServiceOptionGroupPlacement as P;

    $usageLabels = [
        P::USAGE_SECTION => 'قسم — اسم المجموعة = اسم القسم، وخياراتها = فروعه',
        P::USAGE_PRICE_VARIANT => 'أسعار متعددة — للمنتج نفسه سعر لكل خيار',
        P::USAGE_DESCRIPTIVE => 'وصفي — حقل وصف للمنتج (طراز، موسم، نوع بشرة)',
        P::USAGE_COMPONENT => 'مكوّن — ما يُصنع منه المنتج (خشب، قماش) ويُختار لكل صنف',
        P::USAGE_ADDON => 'خدمة مسعّرة — يسعّرها المحل مرة لكل وحدة ويضيفها العميل فوق الصنف (طريقة الطهي)',
        P::USAGE_STORE_TERMS => 'شرط المتجر — سياسة تُحدَّد مرة فى بروفايل المتجر وتظهر عند الشراء (استبدال، حد أدنى)',
        P::USAGE_STORE_CART => 'إعداد عام للمتجر — يظهر فى عربة المشتريات',
        P::USAGE_STORE_FILTER => 'إعداد عام للمتجر — يظهر فى فلتر البحث',
    ];
    $roleLabel = ['line' => 'مسعَّر', 'modifier' => 'معدِّل', 'descriptive' => 'وصفي'];
    $name = fn ($m) => trim((string) ($m->name_ar ?? '')) ?: (trim((string) ($m->name_en ?? '')) ?: ('#' . $m->id));
    $serviceIdx = $services->pluck('id')->search($serviceId);
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('مكونات الخدمة') }}</h1>
            <div class="a2-page-subtitle">
                {{ __('اختر الأب والابن، ثم خدمة خدمة: حدّد لكل مجموعة خيارات إن كانت قسمًا (وخياراتها فروعه)، أو حقلًا وصفيًا (مثل الماركة)، أو أسعارًا متعددة للمنتج نفسه (كاش/قسط، جديد/مستعمل). مكان الظهور تلقائي. الإعدادات لهذا الابن فقط.') }}
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
                <label class="a2-label">{{ __('الأب') }}</label>
                <select class="a2-select" name="root_id" onchange="this.form.child_id.value=0;this.form.service_id.value=0;this.form.submit()">
                    @foreach($roots as $root)
                        <option value="{{ $root->id }}" @selected($rootId === (int) $root->id)>{{ $name($root) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="a2-filter-md">
                <label class="a2-label">{{ __('الابن') }}</label>
                <select class="a2-select" name="child_id" onchange="this.form.service_id.value=0;this.form.submit()">
                    @foreach($children as $child)
                        <option value="{{ $child->id }}" @selected($childId === (int) $child->id)>{{ $name($child) }}</option>
                    @endforeach
                </select>
            </div>
            <input type="hidden" name="service_id" value="{{ $serviceId }}">
        </form>

        <div style="margin-top:12px">
            <span class="a2-label">{{ __('الخدمات المتاحة لهذا الابن') }}</span>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px">
                @forelse($services as $service)
                    <a class="a2-btn {{ $serviceId === (int) $service->id ? 'a2-btn-primary' : 'a2-btn-ghost' }}"
                       href="{{ route('admin.service-components.index', ['root_id' => $rootId, 'child_id' => $childId, 'service_id' => $service->id]) }}">
                        {{ $name($service) }}
                    </a>
                @empty
                    <span class="a2-muted">{{ __('لا توجد خدمات مفعّلة لهذا الابن تحت هذا الأب.') }}</span>
                @endforelse
            </div>
        </div>
    </div>

    @if($serviceId > 0)
        <form method="POST" action="{{ route('admin.service-components.save') }}">
            @csrf
            <input type="hidden" name="root_id" value="{{ $rootId }}">
            <input type="hidden" name="child_id" value="{{ $childId }}">
            <input type="hidden" name="service_id" value="{{ $serviceId }}">

            <div class="a2-card a2-mb-16">
                <div class="a2-table-wrap">
                    <table class="a2-table">
                        <thead>
                            <tr>
                                <th>{{ __('البند (مجموعة الخيارات)') }}</th>
                                <th>{{ __('هو فى الخدمة') }}</th>
                                <th>{{ __('الفروع داخل المجموعة هى أقسام متعددة') }}</th>
                                <th>{{ __('مفعّل') }}</th>
                                <th title="{{ __('للمجموعات «الوصفية» فقط') }}">{{ __('ترتيب الحقل') }}</th>
                                <th title="{{ __('للمجموعات «الوصفية» فقط') }}">{{ __('فى صفحة المنتج') }}</th>
                                <th title="{{ __('للمجموعات «الوصفية» فقط') }}">{{ __('العرض') }}</th>
                                <th title="{{ __('للمجموعات «الوصفية» فقط') }}">{{ __('الاختيار') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($groups as $i => $group)
                                @php
                                    $row = $saved->get($group->id);
                                    $usage = $row->usage ?? ($roleDefaults[$group->price_role] ?? P::USAGE_DESCRIPTIVE);
                                @endphp
                                <tr>
                                    <td>
                                        <input type="hidden" name="rows[{{ $i }}][option_group_id]" value="{{ $group->id }}">
                                        <div class="a2-fw-900">{{ $name($group) }}</div>
                                        <div class="a2-muted">{{ __('نوعها الحالي') }}: {{ $roleLabel[$group->price_role] ?? $group->price_role }}@if(! $row) — {{ __('لم يُحفظ بعد') }}@endif</div>
                                        @if($group->menu_detail_profile_id)
                                            <div class="a2-muted">{{ __('شكل المنيو') }}: <strong>{{ \App\Models\MenuDetailProfile::query()->whereKey($group->menu_detail_profile_id)->value('name_ar') }}</strong> — <a href="{{ route('admin.menu-shapes.index', ['group_id' => $group->id]) }}">{{ __('حقوله من أشكال المنيو') }}</a></div>
                                        @endif
                                    </td>
                                    <td>
                                        <select class="a2-select" name="rows[{{ $i }}][usage]">
                                            @foreach($usageLabels as $key => $label)
                                                <option value="{{ $key }}" @selected($usage === $key)>{{ __($label) }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="hidden" name="rows[{{ $i }}][branches_as_sections]" value="0">
                                        <input type="checkbox" name="rows[{{ $i }}][branches_as_sections]" value="1" @checked($row->branches_as_sections ?? false)
                                            title="{{ __('عند التفعيل: كل فرع (خيار) داخل هذه المجموعة يصبح قسمًا مستقلًا بذاته، بدل أن تكون المجموعة كلها قسمًا واحدًا وفروعها بنوده. مفيد فقط مع «قسم».') }}">
                                    </td>
                                    <td>
                                        <input type="hidden" name="rows[{{ $i }}][is_active]" value="0">
                                        <input type="checkbox" name="rows[{{ $i }}][is_active]" value="1" @checked($row->is_active ?? true)>
                                    </td>
                                    {{-- A DESCRIPTIVE field's own settings — the one home for them: its place among the
                                         others, whether the customer's product page shows it, buttons or a dropdown in the
                                         merchant's form, one choice or several. Live only while the usage is «وصفى». --}}
                                    <td><input class="a2-input" style="width:64px;min-width:0" type="number" min="0" name="rows[{{ $i }}][sort_order]" value="{{ $row->sort_order ?? '' }}" data-desc></td>
                                    <td><input type="hidden" name="rows[{{ $i }}][show_on_page]" value="0"><input type="checkbox" name="rows[{{ $i }}][show_on_page]" value="1" @checked($row->show_on_page ?? true) data-desc></td>
                                    <td><select class="a2-select" style="min-width:92px" name="rows[{{ $i }}][display]" data-desc>@foreach(['auto' => 'تلقائى', 'chips' => 'أزرار', 'dropdown' => 'قائمة'] as $k => $l)<option value="{{ $k }}" @selected(($row->display ?? 'auto') === $k)>{{ __($l) }}</option>@endforeach</select></td>
                                    <td><select class="a2-select" style="min-width:92px" name="rows[{{ $i }}][multiple]" data-desc>@foreach(['1' => 'متعدد', '0' => 'واحد فقط'] as $k => $l)<option value="{{ $k }}" @selected((string) (int) ($row->multiple ?? true) === (string) $k)>{{ __($l) }}</option>@endforeach</select></td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="a2-muted">{{ __('هذا الابن لا يحمل أي مجموعة خيارات. اربطها من «خيارات التصنيفات الفرعية».') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if($groups->isNotEmpty())
                <button class="a2-btn a2-btn-ghost" type="submit">{{ __('حفظ') }}</button>
                @if($serviceIdx !== false && $serviceIdx < $services->count() - 1)
                    <button class="a2-btn a2-btn-primary" type="submit" name="next" value="1">{{ __('حفظ والانتقال للخدمة التالية') }}</button>
                @endif
            @endif
        </form>
    @endif
</div>

<script>
    // The field settings are live only for a «وصفى» group — for any other usage they mean nothing.
    document.querySelectorAll('select[name$="[usage]"]').forEach(function (usage) {
        var row = usage.closest('tr');
        function sync() {
            var on = usage.value === 'descriptive' || usage.value === 'component';
            row.querySelectorAll('[data-desc]').forEach(function (el) { el.disabled = !on; });
            row.classList.toggle('a2-muted', false);
        }
        usage.addEventListener('change', sync);
        sync();
    });
</script>
@endsection
