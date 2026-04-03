@php
    $rowSafe = $row ?? $categoryChild ?? null;

    $parentsSafe = collect($parents ?? []);
    $selectedParentIdsSafe = collect($selectedParentIds ?? [])
        ->map(fn ($id) => (int) $id)
        ->filter(fn ($id) => $id > 0)
        ->values();

    $selectedParents = $parentsSafe->filter(fn ($p) => $selectedParentIdsSafe->contains((int) $p->id))->values();
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
        </div>

        <div>
            <label class="a2-label">الاسم إنجليزي</label>
            <input type="text"
                   name="name_en"
                   class="a2-input"
                   value="{{ old('name_en', $rowSafe->name_en ?? '') }}">
        </div>

        <div>
            <label class="a2-label">الترتيب</label>
            <input type="number"
                   name="reorder"
                   class="a2-input"
                   min="0"
                   value="{{ old('reorder', (int) ($rowSafe->reorder ?? 0)) }}">
        </div>
    </div>
</div>

{{-- =========================
   SELECTED PREVIEW
========================= --}}
<div class="a2-card a2-card--soft a2-mb-16">
    <div class="a2-section-title">المحدد حاليًا</div>
    <div class="a2-section-subtitle">سيتم حفظ هذه العناصر بعد الضغط على حفظ</div>

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
</div>

{{-- =========================
   SEARCH + CHECKBOXES
========================= --}}
<div class="a2-card">
    <div class="a2-card-head">
        <div>
            <div class="a2-section-title a2-mb-0">ربط بالأقسام الرئيسية</div>
            <div class="a2-section-subtitle">
                اختر أو ألغِ التحديد ثم احفظ
            </div>
        </div>
    </div>

    {{-- SEARCH --}}
    <div class="a2-filterbar a2-mt-12">
        <input type="text"
               id="parentSearchInput"
               class="a2-input a2-filter-search"
               placeholder="بحث...">

        <div class="a2-filter-actions">
            <button type="button" class="a2-btn a2-btn-ghost" id="selectVisibleParentsBtn">
                تحديد الظاهر
            </button>

            <button type="button" class="a2-btn a2-btn-ghost" id="clearVisibleParentsBtn">
                إلغاء الظاهر
            </button>
        </div>
    </div>

    {{-- LIST --}}
    <div class="a2-check-grid a2-mt-12" id="parentsCheckGrid">
        @foreach($parentsSafe as $p)
            @php
                $name = $p->name_ar ?: ($p->name_en ?: '—');
                $isChecked = $selectedParentIdsSafe->contains((int) $p->id);
            @endphp

            <label class="a2-check-card js-parent-card"
                   data-parent-name="{{ Str::lower($name . ' ' . $p->id) }}">
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

    const searchInput = document.getElementById('parentSearchInput');
    const cards = Array.from(document.querySelectorAll('.js-parent-card'));
    const checkboxes = Array.from(document.querySelectorAll('.js-parent-checkbox'));
    const previewWrap = document.getElementById('selectedParentsPreview');
    const emptyDynamic = document.getElementById('selectedParentsEmptyDynamic');
    const emptyInitial = document.getElementById('selectedParentsEmptyInitial');

    function checkedBoxes() {
        return checkboxes.filter(cb => cb.checked);
    }

    function refreshPreview() {
        previewWrap.querySelectorAll('.js-parent-chip').forEach(e => e.remove());

        const selected = checkedBoxes();

        if (emptyInitial) emptyInitial.style.display = 'none';

        if (selected.length === 0) {
            emptyDynamic.style.display = '';
            return;
        }

        emptyDynamic.style.display = 'none';

        selected.forEach(cb => {
            const card = cb.closest('.js-parent-card');
            const title = card.querySelector('strong').innerHTML;

            const chip = document.createElement('div');
            chip.className = 'a2-option-chip-card js-parent-chip';
            chip.innerHTML = `<div class="a2-option-chip-title">${title}</div>`;

            previewWrap.appendChild(chip);
        });
    }

    function filter() {
        const val = (searchInput.value || '').toLowerCase();

        cards.forEach(card => {
            const txt = card.dataset.parentName;
            card.style.display = txt.includes(val) ? '' : 'none';
        });
    }

    searchInput?.addEventListener('input', filter);

    checkboxes.forEach(cb => {
        cb.addEventListener('change', refreshPreview);
    });

    document.getElementById('selectVisibleParentsBtn')?.addEventListener('click', () => {
        cards.forEach(card => {
            if (card.style.display !== 'none') {
                card.querySelector('input').checked = true;
            }
        });
        refreshPreview();
    });

    document.getElementById('clearVisibleParentsBtn')?.addEventListener('click', () => {
        cards.forEach(card => {
            if (card.style.display !== 'none') {
                card.querySelector('input').checked = false;
            }
        });
        refreshPreview();
    });

    refreshPreview();
});
</script>
@endpush