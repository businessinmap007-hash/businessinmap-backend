{{--
    من يرى هذا المنتج وسعره.

    «المصنع ينتج ويعرض منتجاته حصريًا للشركات التي يحددها … ولا يستطيع رؤية هذه
     المنتجات وأسعارها إلا الشركات التي حددها، ويمكنه تحديد محلات بعينها.»

    الافتراضُ «للجميع»، وهو ما عليه كلُّ صفٍّ قائم — فلا شىءَ يتغيّر لمن لا
    يفتح هذه البطاقة. والمقيَّدُ لا يظهر لغير من سُمّى: لا فى البحث، ولا فى
    العدّادات، ولا فى السلة.

    والتصنيفُ ليس رفاهية: مصنعٌ له أربعُمئةِ عميل لن يؤشّر أربعمئةَ اسم، وقاعدةٌ
    تُكتب سطرًا سطرًا هى قاعدةٌ لا يكتبها أحد.
--}}
@php
    $current = old('visibility', $row->visibility ?? 'public');
    $picked = [
        'business' => collect(old('audience_business_ids', $audience['business_ids'] ?? []))->map(fn ($i) => (int) $i),
        'child' => collect(old('audience_child_ids', $audience['child_ids'] ?? []))->map(fn ($i) => (int) $i),
        'category' => collect(old('audience_category_ids', $audience['category_ids'] ?? []))->map(fn ($i) => (int) $i),
    ];
@endphp

<div class="a2-card a2-card--section" style="margin-top:14px;">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">{{ __('من يرى هذا المنتج') }}</div>
            <div class="a2-card-sub">{{ __('سعر الجملة ليس سعرًا عامًا. المقيَّد لا يظهر إلا لمن تحدده.') }}</div>
        </div>
    </div>

    <div class="a2-form-grid">
        <div class="a2-form-group a2-field-full">
            <label class="a2-check rv-mode">
                <input type="radio" name="visibility" value="public" @checked($current !== 'restricted')>
                <span><strong>{{ __('للجميع') }}</strong> — {{ __('على الرف، يراه أي زائر.') }}</span>
            </label>
            <label class="a2-check rv-mode">
                <input type="radio" name="visibility" value="restricted" @checked($current === 'restricted')>
                <span><strong>{{ __('لمن أحدده فقط') }}</strong> — {{ __('لا يظهر لغيرهم في البحث ولا في السلة.') }}</span>
            </label>
        </div>
    </div>

    <div class="rv-audience {{ $current === 'restricted' ? '' : 'is-hidden' }}">
        <div class="a2-form-grid">
            <div class="a2-form-group">
                <label class="a2-label" for="audience_category_ids">{{ __('أقسام رئيسية كاملة') }}</label>
                <select class="a2-select" id="audience_category_ids" name="audience_category_ids[]" multiple size="6">
                    @foreach($roots as $id => $name)
                        <option value="{{ $id }}" @selected($picked['category']->contains((int) $id))>{{ $name }}</option>
                    @endforeach
                </select>
                <small class="a2-help">{{ __('مثال: كل الشركات.') }}</small>
            </div>

            <div class="a2-form-group">
                <label class="a2-label" for="audience_child_ids">{{ __('تصنيفات بعينها') }}</label>
                <select class="a2-select" id="audience_child_ids" name="audience_child_ids[]" multiple size="6">
                    @foreach($children as $id => $name)
                        <option value="{{ $id }}" @selected($picked['child']->contains((int) $id))>{{ $name }}</option>
                    @endforeach
                </select>
                <small class="a2-help">{{ __('مثال: كل محلات الأدوات الصحية.') }}</small>
            </div>

            <div class="a2-form-group a2-field-full">
                <label class="a2-label" for="rv_businesses">{{ __('أنشطة بعينها') }}</label>
                {{-- أرقامٌ مفصولة بفواصل: قائمةُ ١٧٤٨ نشاطًا لا تُحمَّل فى الصفحة،
                     والمنتقى بالاسم يحتاج نقطةَ بحثٍ خاصة بلوحة النشاط لا توجد
                     بعد. الرقمُ يعمل اليوم ولا يكذب. --}}
                <input class="a2-input" id="rv_businesses" name="audience_business_ids_csv" dir="ltr"
                       value="{{ $picked['business']->implode(', ') }}"
                       placeholder="123, 456">
                <small class="a2-help">{{ __('أرقام الأنشطة، بينها فاصلة. اتركها فارغة إن كان التحديد بالتصنيف وحده.') }}</small>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    .rv-mode { display: flex; gap: 8px; align-items: flex-start; margin-bottom: 6px; }
    .rv-audience.is-hidden { display: none; }
</style>
@endpush

@push('scripts')
<script>
    (function () {
        var box = document.querySelector('.rv-audience');

        if (!box) {
            return;
        }

        // تظهر لحظةَ اختيار «لمن أحدده»، لا بعد الحفظ.
        document.querySelectorAll('input[name="visibility"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                box.classList.toggle('is-hidden', radio.value !== 'restricted');
            });
        });
    })();
</script>
@endpush
