@php
  $stMap = $statusOptions ?? \App\Models\Booking::statusOptions();

  $unitOptions = [
    '' => '—',
    'minute' => 'Minute',
    'hour'   => 'Hour',
    'day'    => 'Day',
    'week'   => 'Week',
    'month'  => 'Month',
    'year'   => 'Year',
  ];

  $metaText = '';
  if (!empty($booking->meta)) {
    $metaText = json_encode($booking->meta, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
  }

  $startsVal = old('starts_at', $booking->starts_at ? \Carbon\Carbon::parse($booking->starts_at)->format('Y-m-d\TH:i') : '');
  $endsVal   = old('ends_at',   $booking->ends_at   ? \Carbon\Carbon::parse($booking->ends_at)->format('Y-m-d\TH:i')   : '');

  $priceVal  = old('price', $booking->price);

  $oldClientId   = (int) old('user_id', $booking->user_id);
  $oldBusinessId = (int) old('business_id', $booking->business_id);
  $oldServiceId  = (int) old('service_id', $booking->service_id);

  // services passed from controller (collection)
  $servicesJson = collect($services ?? [])->map(function($s){
    return [
      'id' => (int)$s->id,
      'business_id' => (int)$s->business_id,
      'name' => (string)($s->name_ar ?: ($s->name_en ?: ('Service #'.$s->id))),
      'price' => (float)($s->price ?? 0),
      'duration_minutes' => (int)($s->duration ?? 0),
    ];
  })->values()->toJson(JSON_UNESCAPED_UNICODE);
@endphp

@if($errors->any())
  <div class="a2-alert a2-alert-danger">{!! implode('<br>', $errors->all()) !!}</div>
@endif

<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">

  {{-- Client (Admin/Test only) --}}
  <div>
    <label class="a2-label">Client</label>
    <select class="a2-select" name="user_id" id="a2_client_id">
      <option value="">—</option>
      @foreach(($clients ?? collect()) as $c)
        <option value="{{ $c->id }}" @selected((int)$oldClientId === (int)$c->id)>
          #{{ $c->id }} — {{ $c->name }} ({{ $c->type }}) {{ !empty($c->code) ? ' • '.$c->code : '' }}
        </option>
      @endforeach
    </select>
    <div class="a2-hint">في التطبيق سيتم تحديده تلقائيًا.</div>
  </div>

  {{-- Business --}}
  <div>
    <label class="a2-label">Business</label>
    <select class="a2-select" name="business_id" id="a2_business_id" required>
      <option value="">— اختر بزنس —</option>
      @foreach(($businesses ?? collect()) as $b)
        <option value="{{ $b->id }}" @selected((int)$oldBusinessId === (int)$b->id)>
          #{{ $b->id }} — {{ $b->name }} {{ !empty($b->code) ? ' • '.$b->code : '' }}
        </option>
      @endforeach
    </select>
    <div class="a2-hint">سيتم ربط bookable تلقائيًا بالـ Business في الـ API لاحقًا.</div>
  </div>

{{-- Service Autocomplete (Select2-like UX - no libs) --}}
<div style="grid-column:1 / -1;">
  <label class="a2-label">Service (بحث)</label>

  {{-- hidden actual id --}}
  <input type="hidden" name="service_id" id="a2_service_id" value="{{ $oldServiceId ?: '' }}">

  <div style="position:relative;display:flex;gap:10px;align-items:center;">
    <div style="flex:1;position:relative;">
      <input
        class="a2-input"
        id="a2_service_search"
        type="text"
        placeholder="اكتب اسم الخدمة للبحث..."
        autocomplete="off"
      />

      {{-- clear button --}}
      <button type="button" id="a2_service_clear"
              class="a2-btn a2-btn-ghost"
              style="position:absolute;top:50%;transform:translateY(-50%);left:10px;display:none;padding:6px 10px;">
        ✕
      </button>

      {{-- dropdown --}}
      <div id="a2_service_dropdown"
           style="display:none; position:absolute; left:0; right:0; top:calc(100% + 6px);
                  background:#fff; border:1px solid var(--a2-border-2); border-radius:12px;
                  box-shadow:0 12px 30px rgba(16,24,40,.08); max-height:280px; overflow:auto; z-index:60;">
      </div>
    </div>
  </div>

  <div class="a2-hint" id="a2_service_hint">—</div>
</div>

  {{-- Status --}}
  <div>
    <label class="a2-label">status</label>
    <select class="a2-select" name="status">
      @foreach($stMap as $k => $label)
        <option value="{{ $k }}" @selected((string)old('status', $booking->status ?: 'pending') === (string)$k)>{{ $label }}</option>
      @endforeach
    </select>
  </div>

</div>

<div class="a2-card" style="padding:14px;margin-top:12px;">
  <div class="a2-hint" style="margin-bottom:10px;">الوقت / المدة</div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">

    <div>
      <label class="a2-label">starts_at</label>
      <input class="a2-input" id="a2_starts_at" type="datetime-local" name="starts_at" value="{{ $startsVal }}">
    </div>

    <div>
      <label class="a2-label">ends_at (اختياري)</label>
      <input class="a2-input" id="a2_ends_at" type="datetime-local" name="ends_at" value="{{ $endsVal }}">
      <div class="a2-hint">اتركه فارغًا لو الحجز بدون نهاية</div>
    </div>

    <div>
      <label class="a2-label">duration</label>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
        <input class="a2-input" id="a2_dur_val" name="duration_value" value="{{ old('duration_value', $booking->duration_value) }}" placeholder="مثال: 2">
        <select class="a2-select" id="a2_dur_unit" name="duration_unit">
          @foreach($unitOptions as $k => $label)
            <option value="{{ $k }}" @selected((string)old('duration_unit', (string)$booking->duration_unit) === (string)$k)>
              {{ $label }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="a2-hint">لو ends_at فاضي، سيتم حسابه من المدة</div>
    </div>

    <div>
      <label class="a2-label">timezone (اختياري)</label>
      <input class="a2-input" name="timezone"
             value="{{ old('timezone', $booking->timezone ?: 'Africa/Cairo') }}"
             placeholder="Africa/Cairo">
    </div>

    <div style="display:flex;align-items:flex-end;">
      <label class="a2-check" style="display:flex;gap:10px;align-items:center;">
        <input type="checkbox" name="all_day" value="1" @checked((bool)old('all_day', (bool)$booking->all_day))>
        <span>all_day (حجز يوم كامل/فندقي)</span>
      </label>
    </div>

  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px;">

  <div>
    <label class="a2-label">quantity (اختياري)</label>
    <input class="a2-input" name="quantity" value="{{ old('quantity', $booking->quantity) }}" placeholder="مثال: 3 (وحدات/ليالي/جلسات)">
  </div>

  <div>
    <label class="a2-label">party_size (اختياري)</label>
    <input class="a2-input" name="party_size" value="{{ old('party_size', $booking->party_size) }}" placeholder="مثال: 4 أفراد">
  </div>

  <div>
    <label class="a2-label">price (محسوب تلقائيًا)</label>
    <input class="a2-input" id="a2_price" name="price" value="{{ $priceVal }}" readonly>
    <div class="a2-hint" id="a2_price_hint">—</div>
  </div>

</div>

<div style="margin-top:12px;">
  <label class="a2-label">notes</label>
  <textarea class="a2-textarea" name="notes" rows="4">{{ old('notes', $booking->notes) }}</textarea>
</div>

<div style="margin-top:12px;">
  <label class="a2-label">meta (JSON) اختياري</label>
  <textarea class="a2-textarea" name="meta_raw" rows="7" placeholder='مثال:
{
  "booking_kind": "restaurant",
  "menu_items": [{"id": 1, "qty": 2}],
  "special_requests": "بدون بصل"
}
'>{{ old('meta_raw', $metaText) }}</textarea>
  <div class="a2-hint">لو JSON غير صالح سيتم حفظه داخل meta._raw</div>
</div>

<script>
(function(){
  // DATA
  const SERVICES = {!! $servicesJson !!};

  // Elements
  const businessEl   = document.getElementById('a2_business_id');
  const serviceIdEl  = document.getElementById('a2_service_id');
  const searchEl     = document.getElementById('a2_service_search');
  const dropEl       = document.getElementById('a2_service_dropdown');
  const hintEl       = document.getElementById('a2_service_hint');
  const clearBtn     = document.getElementById('a2_service_clear');

  const startsEl     = document.getElementById('a2_starts_at');
  const endsEl       = document.getElementById('a2_ends_at');
  const durValEl     = document.getElementById('a2_dur_val');
  const durUnitEl    = document.getElementById('a2_dur_unit');
  const priceEl      = document.getElementById('a2_price');
  const priceHintEl  = document.getElementById('a2_price_hint');

  // State
  let currentService = null;
  let open = false;
  let activeIndex = -1;
  let lastItems = [];

  function getBusinessId(){
    return parseInt(businessEl?.value || '0', 10) || 0;
  }

  function escapeHtml(s){
    return (s || '').toString()
      .replaceAll('&','&amp;').replaceAll('<','&lt;')
      .replaceAll('>','&gt;').replaceAll('"','&quot;')
      .replaceAll("'","&#039;");
  }

  function toMinutes(val, unit){
    val = parseInt(val || '0', 10);
    if (!val || val <= 0) return null;
    switch(unit){
      case 'minute': return val;
      case 'hour':   return val * 60;
      case 'day':    return val * 60 * 24;
      case 'week':   return val * 60 * 24 * 7;
      case 'month':  return val * 60 * 24 * 30;
      case 'year':   return val * 60 * 24 * 365;
      default:       return null;
    }
  }

  function diffMinutes(startLocal, endLocal){
    if (!startLocal || !endLocal) return null;
    const s = new Date(startLocal);
    const e = new Date(endLocal);
    const ms = e.getTime() - s.getTime();
    if (!isFinite(ms) || ms <= 0) return null;
    return Math.round(ms / 60000);
  }

  function recalcPrice(){
    if (!priceEl || !priceHintEl) return;

    if (!currentService || !currentService.price || !currentService.duration_minutes){
      priceHintEl.textContent = '—';
      return;
    }

    const basePrice = Number(currentService.price) || 0;
    const baseMin   = Number(currentService.duration_minutes) || 0;

    if (basePrice <= 0 || baseMin <= 0){
      priceHintEl.textContent = 'الخدمة بدون سعر/مدة';
      return;
    }

    let minutes = diffMinutes(startsEl?.value, endsEl?.value);
    if (!minutes){
      minutes = toMinutes(durValEl?.value, durUnitEl?.value);
    }

    if (!minutes || minutes <= 0){
      const p = basePrice.toFixed(2);
      priceEl.value = p;
      priceHintEl.textContent = `السعر الأساسي: ${p}`;
      return;
    }

    const ratio = minutes / baseMin;
    const price = basePrice * ratio;

    const p = price.toFixed(2);
    priceEl.value = p;
    priceHintEl.textContent = `محسوب: ${basePrice} × (${minutes} / ${baseMin}) = ${p}`;
  }

  function setService(service){
    currentService = service || null;

    if (!currentService){
      serviceIdEl.value = '';
      searchEl.value = '';
      hintEl.textContent = '—';
      clearBtn.style.display = 'none';
      recalcPrice();
      return;
    }

    serviceIdEl.value = String(currentService.id);
    searchEl.value = currentService.name;
    hintEl.textContent = `Service: ${currentService.name} | Base: ${currentService.price} / ${currentService.duration_minutes} min`;
    clearBtn.style.display = 'inline-flex';
    recalcPrice();
  }

  function filtered(term){
    const bid = getBusinessId();
    term = (term || '').trim().toLowerCase();

    let list = SERVICES.filter(s => !bid || Number(s.business_id) === bid);

    if (term){
      list = list.filter(s => (s.name || '').toLowerCase().includes(term));
    }

    return list.slice(0, 20);
  }

  function render(items){
    lastItems = items || [];
    activeIndex = (lastItems.length ? 0 : -1);

    if (!lastItems.length){
      dropEl.innerHTML = `<div style="padding:10px 12px;color:var(--a2-muted);">لا توجد نتائج</div>`;
      return;
    }

    dropEl.innerHTML = lastItems.map((s, idx) => {
      const active = idx === activeIndex;
      return `
        <div
          role="option"
          data-id="${s.id}"
          data-idx="${idx}"
          style="
            display:flex;gap:10px;align-items:center;justify-content:space-between;
            padding:10px 12px; cursor:pointer;
            background:${active ? 'rgba(99,102,241,.10)' : 'transparent'};
            border-bottom:1px solid var(--a2-border);
          "
        >
          <div style="display:flex;flex-direction:column;gap:2px;min-width:0;">
            <div style="font-weight:800;color:var(--a2-text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
              ${escapeHtml(s.name)}
            </div>
            <div style="font-size:12px;color:var(--a2-muted);">
              #${s.id} • بزنس: ${s.business_id}
            </div>
          </div>

          <div style="display:flex;gap:8px;align-items:center;flex-shrink:0;">
            <span style="font-size:12px;padding:4px 8px;border-radius:999px;border:1px solid var(--a2-border-2);">
              ${s.duration_minutes} min
            </span>
            <span style="font-size:12px;padding:4px 8px;border-radius:999px;border:1px solid var(--a2-border-2);">
              ${Number(s.price || 0).toFixed(2)}
            </span>
          </div>
        </div>
      `;
    }).join('');
  }

  function openDropdown(){
    if (!dropEl) return;
    const items = filtered(searchEl.value);
    render(items);
    dropEl.style.display = 'block';
    open = true;
    ensureActiveVisible();
  }

  function closeDropdown(){
    if (!dropEl) return;
    dropEl.style.display = 'none';
    open = false;
    activeIndex = -1;
  }

  function ensureActiveVisible(){
    if (!open) return;
    const activeEl = dropEl.querySelector(`[data-idx="${activeIndex}"]`);
    if (!activeEl) return;
    const rect = activeEl.getBoundingClientRect();
    const parent = dropEl.getBoundingClientRect();

    if (rect.top < parent.top) activeEl.scrollIntoView({block:'nearest'});
    if (rect.bottom > parent.bottom) activeEl.scrollIntoView({block:'nearest'});
  }

  function selectActive(){
    if (!lastItems.length || activeIndex < 0) return;
    const svc = lastItems[activeIndex];
    if (svc) setService(svc);
    closeDropdown();
  }

  // Events: business change => validate current selection
  businessEl?.addEventListener('change', function(){
    const bid = getBusinessId();
    if (currentService && bid && Number(currentService.business_id) !== bid){
      setService(null);
    }
    // reopen suggestions if typing
    if (document.activeElement === searchEl) openDropdown();
  });

  // Search input events
  searchEl?.addEventListener('focus', function(){
    openDropdown();
  });

  searchEl?.addEventListener('input', function(){
    // typing invalidates selection
    if (serviceIdEl.value && currentService && searchEl.value !== currentService.name){
      serviceIdEl.value = '';
      currentService = null;
      hintEl.textContent = '—';
      clearBtn.style.display = searchEl.value.trim() ? 'inline-flex' : 'none';
      priceHintEl.textContent = '—';
    }
    openDropdown();
  });

  searchEl?.addEventListener('keydown', function(e){
    if (!open && (e.key === 'ArrowDown' || e.key === 'ArrowUp')){
      openDropdown();
      e.preventDefault();
      return;
    }

    if (!open) return;

    if (e.key === 'ArrowDown'){
      e.preventDefault();
      activeIndex = Math.min(activeIndex + 1, lastItems.length - 1);
      openDropdown(); // re-render to update highlight
      ensureActiveVisible();
      return;
    }

    if (e.key === 'ArrowUp'){
      e.preventDefault();
      activeIndex = Math.max(activeIndex - 1, 0);
      openDropdown();
      ensureActiveVisible();
      return;
    }

    if (e.key === 'Enter'){
      e.preventDefault();
      selectActive();
      return;
    }

    if (e.key === 'Escape'){
      e.preventDefault();
      closeDropdown();
      return;
    }
  });

  // Mouse click selection
  dropEl?.addEventListener('mousemove', function(e){
    const row = e.target.closest('[data-idx]');
    if (!row) return;
    const idx = parseInt(row.getAttribute('data-idx') || '-1', 10);
    if (idx >= 0 && idx !== activeIndex){
      activeIndex = idx;
      // lightweight highlight without full re-render:
      Array.from(dropEl.querySelectorAll('[data-idx]')).forEach(el => {
        el.style.background = (parseInt(el.getAttribute('data-idx'),10) === activeIndex)
          ? 'rgba(99,102,241,.10)'
          : 'transparent';
      });
    }
  });

  dropEl?.addEventListener('click', function(e){
    const row = e.target.closest('[data-id]');
    if (!row) return;
    const id = parseInt(row.getAttribute('data-id') || '0', 10);
    const svc = SERVICES.find(x => Number(x.id) === id) || null;
    setService(svc);
    closeDropdown();
  });

  // Clear
  clearBtn?.addEventListener('click', function(){
    setService(null);
    searchEl.focus();
    openDropdown();
  });

  // Close on outside click
  document.addEventListener('click', function(e){
    if (e.target === searchEl) return;
    if (dropEl.contains(e.target)) return;
    if (e.target === clearBtn) return;
    closeDropdown();
  });

  // price recalculation triggers
  startsEl?.addEventListener('change', recalcPrice);
  endsEl?.addEventListener('change', recalcPrice);
  durValEl?.addEventListener('input', recalcPrice);
  durUnitEl?.addEventListener('change', recalcPrice);

  // INIT: preselect by old id
  (function init(){
    const oldId = parseInt(serviceIdEl.value || '0', 10);
    if (oldId > 0){
      const svc = SERVICES.find(x => Number(x.id) === oldId) || null;
      if (svc){
        setService(svc);
        return;
      }
    }
    clearBtn.style.display = 'none';
    recalcPrice();
  })();

})();
</script>