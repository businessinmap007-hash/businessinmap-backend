@php
    $rowSafe = $row ?? $categoryChild ?? null;

    $parentsSafe = collect($parents ?? []);
    $servicesSafe = collect($services ?? []);

    $selectedParentIdsSafe = collect($selectedParentIds ?? [])
        ->map(fn ($id) => (int) $id)
        ->filter(fn ($id) => $id > 0)
        ->values();

    $selectedServiceIdsSafe = collect($selectedServiceIds ?? [])
        ->map(fn ($id) => (int) $id)
        ->filter(fn ($id) => $id > 0)
        ->values();

    $selectedParents = $parentsSafe
        ->filter(fn ($p) => $selectedParentIdsSafe->contains((int) $p->id))
        ->values();

    $selectedServices = $servicesSafe
        ->filter(fn ($s) => $selectedServiceIdsSafe->contains((int) $s->id))
        ->values();
@endphp

{{-- =========================
   BASIC DATA
========================= --}}
<div class="a2-card a2-card--section a2-mb-16">
    <div class="a2-card-head">
        <div>
            <div class="a2-section-title a2-mb-0">بيانات القسم الفرعي</div>
            <div class="a2-section-subtitle">الاسم والترتيب</div>
        </div>
    </div>

    <div class="a2-form-grid a2-mt-12">
        <div>
            <label class="a2-label">الاسم عربي</label>
            <input type="text"
                   name="name_ar"
                   class="a2-input"
                   value="{{ old('name_ar', $rowSafe->name_ar ?? '') }}"
                   required>
            @error('name_ar')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div>
            <label class="a2-label">الاسم إنجليزي</label>
            <input type="text"
                   name="name_en"
                   class="a2-input"
                   value="{{ old('name_en', $rowSafe->name_en ?? '') }}">
            @error('name_en')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div>
            <label class="a2-label">الترتيب</label>
            <input type="number"
                   name="reorder"
                   class="a2-input"
                   min="0"
                   value="{{ old('reorder', (int) ($rowSafe->reorder ?? 0)) }}">
            @error('reorder')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

{{-- =========================
   SELECTED PREVIEW
========================= --}}
<div class="a2-card a2-card--soft a2-mb-16">
    <div class="a2-section-title">المحدد حاليًا</div>
    <div class="a2-section-subtitle">سيتم حفظ هذه العناصر بعد الضغط على حفظ</div>

    <div class="a2-section-title a2-mt-12">الأقسام الرئيسية</div>
    <div id="selectedParentsPreview" class="a2-option-chip-grid a2-mt-12">
        @forelse($selectedParents as $p)
            <div class="a2-option-chip-card js-parent-chip" data-parent-id="{{ $p->id }}">
                <div class="a2-option-chip-title">
                    {{ $p->name_ar ?: ($p->name_en ?: ('#'.$p->id)) }}
                </div>
                <div class="a2-option-chip-sub">
                    #{{ $p->id }}
                </div>
            </div>
        @empty
            <div class="a2-alert a2-alert-warning" id="selectedParentsEmptyInitial">
                لا يوجد تحديد حالي
            </div>
        @endforelse
    </div>

    <div id="selectedParentsEmptyDynamic"
         class="a2-alert a2-alert-warning a2-mt-12"
         style="display:none;">
        لا يوجد عناصر محددة
    </div>

    <div class="a2-divider"></div>

    <div class="a2-section-title a2-mt-12">الخدمات</div>
    <div id="selectedServicesPreview" class="a2-option-chip-grid a2-mt-12">
        @forelse($selectedServices as $s)
            <div class="a2-option-chip-card js-service-chip" data-service-id="{{ $s->id }}">
                <div class="a2-option-chip-title">
                    {{ $s->name_ar ?: ($s->name_en ?: ('#'.$s->id)) }}
                </div>
                <div class="a2-option-chip-sub">
                    #{{ $s->id }}
                </div>
            </div>
        @empty
            <div class="a2-alert a2-alert-warning" id="selectedServicesEmptyInitial">
                لا يوجد خدمات محددة
            </div>
        @endforelse
    </div>

    <div id="selectedServicesEmptyDynamic"
         class="a2-alert a2-alert-warning a2-mt-12"
         style="display:none;">
        لا يوجد خدمات محددة
    </div>
</div>

{{-- =========================
   SEARCH + PARENTS
========================= --}}
<div class="a2-card a2-mb-16">
    <div class="a2-card-head">
        <div>
            <div class="a2-section-title a2-mb-0">ربط بالأقسام الرئيسية</div>
            <div class="a2-section-subtitle">
                اختر أو ألغِ التحديد ثم احفظ
            </div>
        </div>
    </div>

    <div class="a2-filterbar a2-mt-12">
        <input type="text"
               id="parentSearchInput"
               class="a2-input a2-filter-search"
               placeholder="بحث في الأقسام الرئيسية...">

        <div class="a2-filter-actions">
            <button type="button" class="a2-btn a2-btn-ghost" id="selectVisibleParentsBtn">
                تحديد الظاهر
            </button>

            <button type="button" class="a2-btn a2-btn-ghost" id="clearVisibleParentsBtn">
                إلغاء الظاهر
            </button>
        </div>
    </div>

    <div class="a2-check-grid a2-mt-12" id="parentsCheckGrid">
        @foreach($parentsSafe as $p)
            @php
                $name = $p->name_ar ?: ($p->name_en ?: '—');
                $isChecked = $selectedParentIdsSafe->contains((int) $p->id);
            @endphp

            <label class="a2-check-card js-parent-card"
                   data-parent-name="{{ \Illuminate\Support\Str::lower($name . ' ' . $p->id) }}">
                <input type="checkbox"
                       class="js-parent-checkbox"
                       name="parent_ids[]"
                       value="{{ $p->id }}"
                       @checked($isChecked)>

                <span>
                    <strong>#{{ $p->id }} — {{ $name }}</strong>
                </span>
            </label>
        @endforeach
    </div>
</div>

{{-- =========================
   SEARCH + SERVICES
========================= --}}
<div class="a2-card">
    <div class="a2-card-head">
        <div>
            <div class="a2-section-title a2-mb-0">الخدمات المتاحة لهذا القسم الفرعي</div>
            <div class="a2-section-subtitle">
                اختر خدمة واحدة أو أكثر ثم احفظ
            </div>
        </div>
    </div>

    <div class="a2-filterbar a2-mt-12">
        <input type="text"
               id="serviceSearchInput"
               class="a2-input a2-filter-search"
               placeholder="بحث في الخدمات...">

        <div class="a2-filter-actions">
            <button type="button" class="a2-btn a2-btn-ghost" id="selectVisibleServicesBtn">
                تحديد الظاهر
            </button>

            <button type="button" class="a2-btn a2-btn-ghost" id="clearVisibleServicesBtn">
                إلغاء الظاهر
            </button>
        </div>
    </div>

    <div class="a2-check-grid a2-mt-12" id="servicesCheckGrid">
        @forelse($servicesSafe as $s)
            @php
                $serviceName = $s->name_ar ?: ($s->name_en ?: '—');
                $isChecked = $selectedServiceIdsSafe->contains((int) $s->id);
            @endphp

            <label class="a2-check-card js-service-card"
                   data-service-name="{{ \Illuminate\Support\Str::lower($serviceName . ' ' . $s->id) }}">
                <input type="checkbox"
                       class="js-service-checkbox"
                       name="service_ids[]"
                       value="{{ $s->id }}"
                       @checked($isChecked)>

                <span>
                    <strong>#{{ $s->id }} — {{ $serviceName }}</strong>
                </span>
            </label>
        @empty
            <div class="a2-alert a2-alert-warning">
                لا توجد خدمات متاحة حاليًا.
            </div>
        @endforelse
    </div>
</div>

{{-- =========================
   ACTIONS
========================= --}}
<div class="a2-page-actions a2-mt-16" style="justify-content:flex-end;">
    <button type="submit" class="a2-btn a2-btn-primary">
        حفظ
    </button>
</div>

{{-- =========================
   SCRIPT
========================= --}}
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Parents
    const parentSearchInput = document.getElementById('parentSearchInput');
    const parentCards = Array.from(document.querySelectorAll('.js-parent-card'));
    const parentCheckboxes = Array.from(document.querySelectorAll('.js-parent-checkbox'));
    const parentPreviewWrap = document.getElementById('selectedParentsPreview');
    const parentEmptyDynamic = document.getElementById('selectedParentsEmptyDynamic');
    const parentEmptyInitial = document.getElementById('selectedParentsEmptyInitial');

    function checkedParentBoxes() {
        return parentCheckboxes.filter(cb => cb.checked);
    }

    function refreshParentsPreview() {
        parentPreviewWrap.querySelectorAll('.js-parent-chip').forEach(e => e.remove());

        const selected = checkedParentBoxes();

        if (parentEmptyInitial) parentEmptyInitial.style.display = 'none';

        if (selected.length === 0) {
            parentEmptyDynamic.style.display = '';
            return;
        }

        parentEmptyDynamic.style.display = 'none';

        selected.forEach(cb => {
            const card = cb.closest('.js-parent-card');
            const title = card.querySelector('strong').innerHTML;

            const chip = document.createElement('div');
            chip.className = 'a2-option-chip-card js-parent-chip';
            chip.innerHTML = `<div class="a2-option-chip-title">${title}</div>`;

            parentPreviewWrap.appendChild(chip);
        });
    }

    function filterParents() {
        const val = (parentSearchInput.value || '').toLowerCase();

        parentCards.forEach(card => {
            const txt = card.dataset.parentName || '';
            card.style.display = txt.includes(val) ? '' : 'none';
        });
    }

    parentSearchInput?.addEventListener('input', filterParents);

    parentCheckboxes.forEach(cb => {
        cb.addEventListener('change', refreshParentsPreview);
    });

    document.getElementById('selectVisibleParentsBtn')?.addEventListener('click', () => {
        parentCards.forEach(card => {
            if (card.style.display !== 'none') {
                const input = card.querySelector('input');
                if (input) input.checked = true;
            }
        });
        refreshParentsPreview();
    });

    document.getElementById('clearVisibleParentsBtn')?.addEventListener('click', () => {
        parentCards.forEach(card => {
            if (card.style.display !== 'none') {
                const input = card.querySelector('input');
                if (input) input.checked = false;
            }
        });
        refreshParentsPreview();
    });

    refreshParentsPreview();

    // Services
    const serviceSearchInput = document.getElementById('serviceSearchInput');
    const serviceCards = Array.from(document.querySelectorAll('.js-service-card'));
    const serviceCheckboxes = Array.from(document.querySelectorAll('.js-service-checkbox'));
    const servicePreviewWrap = document.getElementById('selectedServicesPreview');
    const serviceEmptyDynamic = document.getElementById('selectedServicesEmptyDynamic');
    const serviceEmptyInitial = document.getElementById('selectedServicesEmptyInitial');

    function checkedServiceBoxes() {
        return serviceCheckboxes.filter(cb => cb.checked);
    }

    function refreshServicesPreview() {
        servicePreviewWrap.querySelectorAll('.js-service-chip').forEach(e => e.remove());

        const selected = checkedServiceBoxes();

        if (serviceEmptyInitial) serviceEmptyInitial.style.display = 'none';

        if (selected.length === 0) {
            serviceEmptyDynamic.style.display = '';
            return;
        }

        serviceEmptyDynamic.style.display = 'none';

        selected.forEach(cb => {
            const card = cb.closest('.js-service-card');
            const title = card.querySelector('strong').innerHTML;

            const chip = document.createElement('div');
            chip.className = 'a2-option-chip-card js-service-chip';
            chip.innerHTML = `<div class="a2-option-chip-title">${title}</div>`;

            servicePreviewWrap.appendChild(chip);
        });
    }

    function filterServices() {
        const val = (serviceSearchInput.value || '').toLowerCase();

        serviceCards.forEach(card => {
            const txt = card.dataset.serviceName || '';
            card.style.display = txt.includes(val) ? '' : 'none';
        });
    }

    serviceSearchInput?.addEventListener('input', filterServices);

    serviceCheckboxes.forEach(cb => {
        cb.addEventListener('change', refreshServicesPreview);
    });

    document.getElementById('selectVisibleServicesBtn')?.addEventListener('click', () => {
        serviceCards.forEach(card => {
            if (card.style.display !== 'none') {
                const input = card.querySelector('input');
                if (input) input.checked = true;
            }
        });
        refreshServicesPreview();
    });

    document.getElementById('clearVisibleServicesBtn')?.addEventListener('click', () => {
        serviceCards.forEach(card => {
            if (card.style.display !== 'none') {
                const input = card.querySelector('input');
                if (input) input.checked = false;
            }
        });
        refreshServicesPreview();
    });

    refreshServicesPreview();
});
</script>
@endpush