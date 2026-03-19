@extends('admin-v2.layouts.master')

@section('title', 'Calendar')
@section('body_class', 'admin-v2-bookable-calendar')

@section('content')
@include('admin-v2.bookable-items.partials.tabs', ['item' => $bookableItem])

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">التقويم والتوافر</h1>
            <div class="a2-page-subtitle">
                {{ $bookableItem->title }} — {{ $monthStart->translatedFormat('F Y') }}
            </div>
        </div>

        <div class="a2-page-actions">
            <a class="a2-btn a2-btn-ghost"
               href="{{ route('admin.bookable-items.calendar', ['bookableItem' => $bookableItem->id, 'month' => $prevMonth, 'year' => $prevYear]) }}">
                الشهر السابق
            </a>

            <a class="a2-btn a2-btn-ghost"
               href="{{ route('admin.bookable-items.calendar', ['bookableItem' => $bookableItem->id, 'month' => now()->month, 'year' => now()->year]) }}">
                هذا الشهر
            </a>

            <a class="a2-btn a2-btn-ghost"
               href="{{ route('admin.bookable-items.calendar', ['bookableItem' => $bookableItem->id, 'month' => $nextMonth, 'year' => $nextYear]) }}">
                الشهر التالي
            </a>
        </div>
    </div>

    <div class="a2-alert a2-alert-warning a2-bookcal-alert">
        يمكنك اختيار يوم واحد أو سحب نطاق من الأيام، ثم إضافة غلق أو قاعدة سعر على النطاق المحدد.
    </div>

    <div class="a2-bookcal-layout">
        <div class="a2-card a2-bookcal-main-card">
            <div class="a2-bookcal-weekdays">
                <div>السبت</div>
                <div>الأحد</div>
                <div>الاثنين</div>
                <div>الثلاثاء</div>
                <div>الأربعاء</div>
                <div>الخميس</div>
                <div>الجمعة</div>
            </div>

            <div class="a2-bookcal-grid">
                @foreach($days as $day)
                    <button
                        type="button"
                        class="a2-bookcal-day
                            {{ !$day['is_current_month'] ? ' is-muted' : '' }}
                            {{ !empty($day['is_today']) ? ' is-today' : '' }}
                            {{ !empty($day['is_blocked']) ? ' is-blocked' : '' }}
                            {{ !empty($day['has_rule']) ? ' has-rule' : '' }}"
                        data-date="{{ $day['date'] }}"
                    >
                       <div class="a2-bookcal-day-top">
    <span class="a2-bookcal-day-num">{{ $day['day'] }}</span>
</div>

@if(isset($day['final_price']))
    <div class="a2-bookcal-price-wrap">
        <span class="a2-bookcal-price">
            {{ number_format((float) $day['final_price'], 2) }}
            <small>{{ $day['currency'] ?? 'EGP' }}</small>
        </span>
    </div>
@endif

                        <div class="a2-bookcal-flags">
                            @if(!empty($day['is_blocked']))
                                <span class="a2-pill a2-pill-danger">Blocked</span>
                            @endif

                            @if(!empty($day['has_rule']))
                                <span class="a2-pill a2-pill-warning">Rule</span>
                            @endif
                        </div>

                      @if(!empty($day['rules']) && isset($day['rules'][0]))
    <div class="a2-bookcal-rule-note">
        {{ $day['rules'][0]['title'] ?: $day['rules'][0]['rule_type'] }}
    </div>
@endif

                        <div class="a2-bookcal-day-badges">
                            @if(!empty($day['blocked_count']))
                                <span class="a2-pill a2-pill-inactive">Closed</span>
                            @endif

                            @if(!empty($day['price_rules_count']))
                                <span class="a2-pill a2-pill-gray">Price</span>
                            @endif
                        </div>
                    </button>
                @endforeach
            </div>
        </div>

        <div class="a2-card a2-bookcal-side" id="a2BookcalSide">
            <div class="a2-card-head">
                <div>
                    <div class="a2-card-title">تفاصيل التحديد</div>
                    <div class="a2-card-sub">اليوم أو النطاق المحدد حاليًا</div>
                </div>
            </div>

            <div id="a2BookcalEmpty" class="a2-hint">
                اختر يومًا أو اسحب على أكثر من يوم لعرض التفاصيل أو إضافة غلق/تسعير.
            </div>

            <div id="a2BookcalPanel" class="a2-hidden">
                <div class="a2-bookcal-picked-date" id="a2BookcalPickedDate"></div>

                <div class="a2-bookcal-info-block">
                    <div class="a2-section-subtitle">Blocked Slots</div>
                    <div id="a2BookcalBlockedList" class="a2-bookcal-list"></div>
                </div>

                <div class="a2-bookcal-info-block">
                    <div class="a2-section-subtitle">Price Rules</div>
                    <div id="a2BookcalRulesList" class="a2-bookcal-list"></div>
                </div>

                <div class="a2-divider"></div>

                <form method="POST"
                      action="{{ route('admin.bookable-items.calendar.blocked-slot.store', $bookableItem) }}"
                      class="a2-bookcal-form">
                    @csrf

                    <div class="a2-section-title">إضافة غلق</div>

                    <input type="hidden" name="starts_at" id="a2BlockStartsAt">
                    <input type="hidden" name="ends_at" id="a2BlockEndsAt">

                    <div class="a2-form-group">
                        <label class="a2-label">Type</label>
                        <select class="a2-select" name="block_type">
                            <option value="manual">manual</option>
                            <option value="maintenance">maintenance</option>
                            <option value="holiday">holiday</option>
                            <option value="admin">admin</option>
                        </select>
                    </div>

                    <div class="a2-form-group">
                        <label class="a2-label">Reason</label>
                        <input class="a2-input" name="reason" placeholder="سبب الغلق">
                    </div>

                    <div class="a2-form-group">
                        <label class="a2-label">Notes</label>
                        <textarea class="a2-textarea" name="notes" rows="3"></textarea>
                    </div>

                    <button class="a2-btn a2-btn-primary a2-btn-block" type="submit">
                        إضافة غلق للنطاق المحدد
                    </button>
                </form>

                <div class="a2-divider"></div>

                <form method="POST"
                      action="{{ route('admin.bookable-items.calendar.price-rule.store', $bookableItem) }}"
                      class="a2-bookcal-form">
                    @csrf

                    <div class="a2-section-title">إضافة سعر</div>

                    <input type="hidden" name="start_date" id="a2PriceStartDate">
                    <input type="hidden" name="end_date" id="a2PriceEndDate">

                    <div class="a2-form-group">
                        <label class="a2-label">Title</label>
                        <input class="a2-input" name="title" placeholder="اسم القاعدة">
                    </div>

                    <div class="a2-form-group">
                        <label class="a2-label">Rule Type</label>
                        <select class="a2-select" name="rule_type">
                            <option value="date_range">date_range</option>
                            <option value="special_day">special_day</option>
                            <option value="season">season</option>
                        </select>
                    </div>

                    <div class="a2-form-grid">
                        <div class="a2-form-group">
                            <label class="a2-label">Price Type</label>
                            <select class="a2-select" name="price_type">
                                <option value="fixed">fixed</option>
                                <option value="delta">delta</option>
                                <option value="percent">percent</option>
                            </select>
                        </div>

                        <div class="a2-form-group">
                            <label class="a2-label">Price Value</label>
                            <input class="a2-input" type="number" step="0.01" name="price_value" required>
                        </div>
                    </div>

                    <div class="a2-form-grid">
                        <div class="a2-form-group">
                            <label class="a2-label">Currency</label>
                            <input class="a2-input" name="currency" value="EGP">
                        </div>

                        <div class="a2-form-group">
                            <label class="a2-label">Priority</label>
                            <input class="a2-input" type="number" name="priority" value="100">
                        </div>
                    </div>

                    <div class="a2-form-group">
                        <label class="a2-label">Notes</label>
                        <textarea class="a2-textarea" name="notes" rows="3"></textarea>
                    </div>

                    <button class="a2-btn a2-btn-dark a2-btn-block" type="submit">
                        إضافة سعر للنطاق المحدد
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>





@push('scripts')
<script>
(function () {
    const days = Array.from(document.querySelectorAll('.a2-bookcal-day'));
    const calendarDays = @json($days);

    let isDragging = false;
    let startDate = null;
    let endDate = null;

    const blockStartsAt = document.getElementById('a2BlockStartsAt');
    const blockEndsAt   = document.getElementById('a2BlockEndsAt');
    const priceStart    = document.getElementById('a2PriceStartDate');
    const priceEnd      = document.getElementById('a2PriceEndDate');

    const emptyBox      = document.getElementById('a2BookcalEmpty');
    const panel         = document.getElementById('a2BookcalPanel');
    const pickedDate    = document.getElementById('a2BookcalPickedDate');
    const blockedList   = document.getElementById('a2BookcalBlockedList');
    const rulesList     = document.getElementById('a2BookcalRulesList');

    function clearRange() {
        days.forEach(function (d) {
            d.classList.remove('is-range-start', 'is-range-end', 'is-in-range');
        });
    }

    function normalizeRange() {
        if (!startDate) return;

        if (endDate && endDate < startDate) {
            const tmp = startDate;
            startDate = endDate;
            endDate = tmp;
        }
    }

    function highlightRange() {
        clearRange();
        if (!startDate) return;

        normalizeRange();

        days.forEach(function (d) {
            const date = d.dataset.date;

            if (date === startDate) {
                d.classList.add('is-range-start');
            }

            if (endDate && date === endDate) {
                d.classList.add('is-range-end');
            }

            if (startDate && endDate && date > startDate && date < endDate) {
                d.classList.add('is-in-range');
            }
        });
    }

    function updateForms() {
        if (!startDate) return;

        normalizeRange();

        const rangeEnd = endDate || startDate;
        const startDateTime = startDate + ' 00:00:00';
        const endDateTime = rangeEnd + ' 23:59:59';

        if (blockStartsAt) blockStartsAt.value = startDateTime;
        if (blockEndsAt)   blockEndsAt.value = endDateTime;

        if (priceStart) priceStart.value = startDate;
        if (priceEnd)   priceEnd.value = rangeEnd;
    }

    function findDayData(date) {
        return calendarDays.find(function (d) {
            return d.date === date;
        }) || null;
    }

    function escapeHtml(value) {
        return String(value || '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function renderBlockedList(dayData) {
        if (!blockedList) return;

        if (!dayData || !Array.isArray(dayData.blocked) || dayData.blocked.length === 0) {
            blockedList.innerHTML = '<div class="a2-hint">لا توجد فترات غلق لهذا اليوم.</div>';
            return;
        }

        blockedList.innerHTML = dayData.blocked.map(function (slot) {
            return `
                <div class="a2-card a2-card--soft" style="padding:10px;margin-bottom:8px;">
                    <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;">
                        <div>
                            <div style="font-weight:800;" dir="ltr">${escapeHtml(slot.block_type || '-')}</div>
                            <div class="a2-section-subtitle" style="margin:4px 0 0 0;" dir="ltr">
                                ${escapeHtml(slot.starts_at || '-')} → ${escapeHtml(slot.ends_at || '-')}
                            </div>
                            <div style="margin-top:6px;">${escapeHtml(slot.reason || '—')}</div>
                        </div>
                        <div>
                            <a class="a2-btn a2-btn-ghost a2-btn-sm" href="${escapeHtml(slot.edit_url || '#')}">Edit</a>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    function renderRulesList(dayData) {
        if (!rulesList) return;

        if (!dayData || !Array.isArray(dayData.rules) || dayData.rules.length === 0) {
            rulesList.innerHTML = '<div class="a2-hint">لا توجد قواعد سعر لهذا اليوم.</div>';
            return;
        }

        rulesList.innerHTML = dayData.rules.map(function (rule) {
            return `
                <div class="a2-card a2-card--soft" style="padding:10px;margin-bottom:8px;">
                    <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;">
                        <div>
                            <div style="font-weight:800;">${escapeHtml(rule.title || 'Untitled Rule')}</div>
                            <div class="a2-section-subtitle" style="margin:4px 0 0 0;" dir="ltr">
                                ${escapeHtml(rule.rule_type || '-')} / ${escapeHtml(rule.price_type || '-')}
                            </div>
                            <div style="margin-top:6px;" dir="ltr">
                                ${escapeHtml(rule.price_value || 0)} ${escapeHtml(rule.currency || 'EGP')}
                            </div>
                        </div>
                        <div>
                            <a class="a2-btn a2-btn-ghost a2-btn-sm" href="${escapeHtml(rule.edit_url || '#')}">Edit</a>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    function renderSelectionDetails() {
        if (!startDate) {
            if (emptyBox) emptyBox.classList.remove('a2-hidden');
            if (panel) panel.classList.add('a2-hidden');
            return;
        }

        normalizeRange();

        if (emptyBox) emptyBox.classList.add('a2-hidden');
        if (panel) panel.classList.remove('a2-hidden');

        const rangeEnd = endDate || startDate;
        const isSingle = startDate === rangeEnd;

        if (pickedDate) {
            pickedDate.textContent = isSingle
                ? 'اليوم المحدد: ' + startDate
                : 'النطاق المحدد: ' + startDate + ' → ' + rangeEnd;
        }

        const dayData = findDayData(startDate);
        renderBlockedList(dayData);
        renderRulesList(dayData);
    }

    function applySingleDate(date) {
        startDate = date;
        endDate = null;

        highlightRange();
        updateForms();
        renderSelectionDetails();
    }

    days.forEach(function (btn) {
        btn.addEventListener('mousedown', function () {
            isDragging = true;
            startDate = btn.dataset.date;
            endDate = null;

            highlightRange();
            updateForms();
            renderSelectionDetails();
        });

        btn.addEventListener('mouseenter', function () {
            if (!isDragging) return;

            endDate = btn.dataset.date;
            highlightRange();
            updateForms();
            renderSelectionDetails();
        });

        btn.addEventListener('mouseup', function () {
            if (!isDragging) return;

            endDate = btn.dataset.date;
            isDragging = false;

            normalizeRange();
            highlightRange();
            updateForms();
            renderSelectionDetails();
        });

        btn.addEventListener('click', function () {
            applySingleDate(btn.dataset.date);
        });
    });

    document.addEventListener('mouseup', function () {
        isDragging = false;
    });

    renderSelectionDetails();
})();
</script>
@endpush
@endsection