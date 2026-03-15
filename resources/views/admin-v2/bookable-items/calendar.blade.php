@extends('admin-v2.layouts.master')

@section('title', 'Calendar')
@section('body_class', 'admin-v2-bookable-calendar')

@section('content')
@include('admin-v2.bookable-items.partials.tabs', ['item' => $bookableItem])

<div class="a2-page">
    
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">Availability Calendar</h1>
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

    <div class="a2-bookcal-layout">
        <div class="a2-card">
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
                        class="a2-bookcal-day"
                        data-date="{{ $day['date'] }}"
                    >

                        <div class="a2-bookcal-day-num">{{ $day['day'] }}</div>

                        <div class="a2-bookcal-day-badges">
                            @if($day['blocked_count'])
                                <span class="a2-pill a2-pill-inactive">Closed</span>
                            @endif

                            @if($day['price_rules_count'])
                                <span class="a2-pill a2-pill-gray">Price</span>
                            @endif
                        </div>
                    </button>
                @endforeach
            </div>
        </div>

        <div class="a2-card a2-bookcal-side" id="a2BookcalSide">
            <div class="a2-header">
                <h3 class="a2-section-title">تفاصيل اليوم</h3>
            </div>

            <div id="a2BookcalEmpty" class="a2-hint">
                اختر يومًا من التقويم لعرض التفاصيل أو إضافة غلق/تسعير.
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
                        إضافة غلق لهذا اليوم
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
                        إضافة سعر لهذا اليوم
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function(){

const days = Array.from(document.querySelectorAll('.a2-bookcal-day'));

let isDragging = false;
let startDate = null;
let endDate = null;

const blockStartsAt = document.getElementById('a2BlockStartsAt');
const blockEndsAt   = document.getElementById('a2BlockEndsAt');
const priceStart    = document.getElementById('a2PriceStartDate');
const priceEnd      = document.getElementById('a2PriceEndDate');

function clearRange(){
  days.forEach(d=>{
    d.classList.remove('is-range-start','is-range-end','is-in-range','is-drag-hover');
  });
}

function highlightRange(){
  clearRange();
  if(!startDate) return;

  days.forEach(d=>{
    const date = d.dataset.date;

    if(date === startDate){
      d.classList.add('is-range-start');
    }

    if(endDate && date === endDate){
      d.classList.add('is-range-end');
    }

    if(startDate && endDate){
      if(date > startDate && date < endDate){
        d.classList.add('is-in-range');
      }
    }
  });
}

function updateForms(){
  if(!startDate) return;

  const start = startDate + " 00:00:00";
  const end   = (endDate || startDate) + " 23:59:59";

  if(blockStartsAt) blockStartsAt.value = start;
  if(blockEndsAt)   blockEndsAt.value   = end;

  if(priceStart) priceStart.value = startDate;
  if(priceEnd)   priceEnd.value   = (endDate || startDate);
}

function setRange(date){
  if(!startDate){
    startDate = date;
    endDate = null;
  }else{
    if(date < startDate){
      endDate = startDate;
      startDate = date;
    }else{
      endDate = date;
    }
  }

  highlightRange();
  updateForms();
}

days.forEach(btn=>{

  btn.addEventListener('mousedown', e=>{
    isDragging = true;
    startDate = btn.dataset.date;
    endDate = null;
    highlightRange();
  });

  btn.addEventListener('mouseenter', ()=>{
    if(!isDragging) return;

    endDate = btn.dataset.date;
    highlightRange();
  });

  btn.addEventListener('mouseup', ()=>{
    if(!isDragging) return;

    endDate = btn.dataset.date;
    isDragging = false;

    if(endDate < startDate){
      const tmp = startDate;
      startDate = endDate;
      endDate = tmp;
    }

    highlightRange();
    updateForms();
  });

  // click fallback
  btn.addEventListener('click', ()=>{
    setRange(btn.dataset.date);
  });

});

document.addEventListener('mouseup', ()=>{
  isDragging = false;
});

})();
</script>

@endpush
@endsection
