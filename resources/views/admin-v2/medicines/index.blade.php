@extends('admin-v2.layouts.master')

@section('title', 'قاموس الأدوية')
@section('body_class', 'admin-v2 admin-v2-medicines')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('قاموس الأدوية') }}</h1>
            <div class="a2-page-subtitle">
                {{ __('ما يكتبه الطبيب في الروشتة يُقترح من هنا. القاموس ينمو وحده مع كل وصفة — والرفع يملؤه دفعة واحدة.') }}
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success" style="white-space:pre-wrap;">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="a2-alert a2-alert-danger" style="white-space:pre-wrap;">{{ session('error') }}</div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px;">
        <div class="a2-card" style="flex:1;min-width:180px;">
            <div class="a2-muted" style="font-size:12px;">{{ __('إجمالي الأصناف') }}</div>
            <div style="font-size:26px;font-weight:700;">{{ number_format($total) }}</div>
        </div>
        <div class="a2-card" style="flex:1;min-width:180px;">
            <div class="a2-muted" style="font-size:12px;">{{ __('كُتب في روشتة فعلًا') }}</div>
            <div style="font-size:26px;font-weight:700;">{{ number_format($prescribed) }}</div>
        </div>
    </div>

    {{-- «هل يمكن اضافة جدول ونضيف به جميع الادوية الموجودة فى مصر».

         The parsing lives in `medicines:import` and this screen calls it, so
         the panel and the console can never disagree about what a valid row
         is — and nothing here invents a drug: only what is in the file. --}}
    <div class="a2-card" style="margin-bottom:16px;">
        <div class="a2-section-head">
            <div>
                <h2 class="a2-section-title">{{ __('رفع سجل أدوية') }}</h2>
                <div class="a2-section-subtitle">
                    {{ __('ملف CSV أو JSON. يكفي عمود للاسم (name / trade name / الدواء)، والتركيز اختياري (strength / التركيز).') }}
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.medicines.import') }}" enctype="multipart/form-data">
            @csrf

            <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
                <div style="flex:1;min-width:240px;">
                    <label class="a2-label" for="file">{{ __('الملف') }}</label>
                    <input class="a2-input" type="file" id="file" name="file" accept=".csv,.txt,.json" required>
                </div>

                <label class="a2-check-card">
                    <input type="checkbox" name="dry_run" value="1" checked>
                    <span>{{ __('معاينة أولًا (لا يُكتب شيء)') }}</span>
                </label>

                <button type="submit" class="a2-btn a2-btn-primary">{{ __('رفع') }}</button>
            </div>

            <div class="a2-muted a2-mt-12" style="font-size:12px;">
                {{ __('إعادة رفع نفس الملف آمنة — الاسم والتركيز معًا هما الهوية، فلا يتكرر صنف.') }}
            </div>
        </form>
    </div>

    {{-- «اعطنى فيو ارى منهم الدواء واجرب البحث».

         Not a mock-up of the doctor's typeahead — the SAME query. Both this and
         the app endpoint call Medicine::scopeSearch, so a preview cannot end up
         flattering a search the doctor is not actually given. --}}
    <div class="a2-card" style="margin-bottom:16px;">
        <div class="a2-section-head">
            <div>
                <h2 class="a2-section-title">{{ __('جرّب البحث كما يراه الطبيب') }}</h2>
                <div class="a2-section-subtitle">
                    {{ __('اكتب حرفين فأكثر — هذه نفس النتائج التي تظهر للطبيب داخل الروشتة، بنفس الترتيب.') }}
                </div>
            </div>
        </div>

        <input class="a2-input" id="mdTry" autocomplete="off"
               placeholder="{{ __('اكتب اسم دواء… مثل AUGMENTIN أو 500 MG') }}"
               style="font-size:16px;">

        <div class="a2-muted a2-mt-8" id="mdMeta" style="font-size:12px;min-height:16px;"></div>
        <div id="mdOut" class="a2-mt-8"></div>
    </div>

    <div class="a2-card">
        <form method="GET" style="display:flex;gap:8px;margin-bottom:12px;">
            <input class="a2-input" name="q" value="{{ $q }}" placeholder="{{ __('ابحث باسم الدواء') }}" style="max-width:320px;">
            <button class="a2-btn a2-btn-ghost">{{ __('بحث') }}</button>
        </form>

        @if($rows->isEmpty())
            <div class="a2-muted">
                {{ $q !== '' ? __('لا نتائج لهذا البحث.') : __('القاموس فارغ — ارفع سجلًا بالأعلى، أو اتركه ينمو مع أول روشتة.') }}
            </div>
        @else
            <div class="a2-table-wrap">
                <table class="a2-table">
                    <thead>
                        <tr>
                            <th>{{ __('الدواء') }}</th>
                            <th>{{ __('المادة الفعّالة') }}</th>
                            <th>{{ __('الشركة') }}</th>
                            <th>{{ __('السعر') }}</th>
                            <th>{{ __('مرات الكتابة') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            <tr>
                                <td>
                                    {{ $row->name }}
                                    @if($row->strength)
                                        <div class="a2-muted" style="font-size:12px;">{{ $row->strength }}</div>
                                    @endif
                                </td>
                                <td style="font-size:12px;">{{ $row->scientific_name ?: '—' }}</td>
                                <td style="font-size:12px;">{{ $row->manufacturer ?: '—' }}</td>
                                <td style="white-space:nowrap;">
                                    @if($row->price_egp !== null)
                                        {{ rtrim(rtrim(number_format((float) $row->price_egp, 2), '0'), '.') }}
                                        {{-- The date matters: the register says its prices change constantly. --}}
                                        @if($row->price_captured_at)
                                            <div class="a2-muted" style="font-size:11px;">{{ $row->price_captured_at->toDateString() }}</div>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ (int) $row->uses_count }}</td>
                                <td style="text-align:end;">
                                    @if((int) $row->uses_count === 0)
                                        <form method="POST" action="{{ route('admin.medicines.destroy', $row->id) }}"
                                              onsubmit="return confirm('{{ __('حذف هذا الدواء من القاموس؟') }}');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="a2-btn a2-btn-ghost">{{ __('حذف') }}</button>
                                        </form>
                                    @else
                                        <span class="a2-muted" style="font-size:12px;">{{ __('مستعمل في روشتات') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="a2-mt-12">{{ $rows->links() }}</div>
        @endif
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const box = document.getElementById('mdTry');
    const out = document.getElementById('mdOut');
    const meta = document.getElementById('mdMeta');

    if (!box) {
        return;
    }

    // route(..., false): an absolute URL built from APP_URL points at a host the
    // panel may not be served from, and the fetch dies on the cross-origin check
    // with nothing on screen to say why.
    const ENDPOINT = @json(route('admin.medicines.search', [], false));

    const SAYS = {
        empty: @json(__('لا يوجد دواء بهذا الاسم — يستطيع الطبيب إضافته بنفسه وقتها.')),
        typing: @json(__('اكتب حرفين على الأقل.')),
        failed: @json(__('تعذّر البحث.')),
        used: @json(__('كُتب')),
    };

    // Which axis brought the row back. «بالمادة الفعّالة» is the one worth
    // naming: a doctor who typed DICLOFENAC and got twenty brand names should
    // be told that is what happened rather than left to guess.
    const WHY = {
        name: @json(__('بالاسم')),
        scientific: @json(__('بالمادة الفعّالة')),
        arabic: @json(__('بالعربية')),
    };

    let seq = 0;
    let timer = null;

    function render(term, rows) {
        out.innerHTML = '';

        if (!rows.length) {
            const none = document.createElement('div');
            none.className = 'a2-muted';
            none.textContent = SAYS.empty;
            out.appendChild(none);

            return;
        }

        const upper = term.toUpperCase();

        rows.forEach(function (row) {
            const line = document.createElement('div');
            line.style.padding = '8px 10px';
            line.style.borderBottom = '1px solid var(--a2-border,#eef0f4)';

            const top = document.createElement('div');
            top.style.display = 'flex';
            top.style.alignItems = 'center';
            top.style.gap = '8px';

            const name = document.createElement('span');
            name.style.flex = '1';

            // Show WHERE the match landed — the whole reason the search stopped
            // being prefix-only is that this register writes the dose into the
            // name, so most real matches are in the middle of it.
            const mark = function (text) {
                const holder = document.createElement('span');
                const at = String(text || '').toUpperCase().indexOf(upper);

                if (at < 0 || !text) {
                    holder.textContent = text || '';

                    return holder;
                }

                holder.append(text.slice(0, at));
                const hit = document.createElement('mark');
                hit.textContent = text.slice(at, at + term.length);
                holder.appendChild(hit);
                holder.append(text.slice(at + term.length));

                return holder;
            };

            name.appendChild(mark(row.name));
            top.appendChild(name);

            const why = document.createElement('span');
            why.className = 'a2-badge';
            why.textContent = WHY[row.matched_on] || WHY.name;
            top.appendChild(why);

            if (Number(row.uses_count) > 0) {
                const used = document.createElement('span');
                used.className = 'a2-badge';
                used.textContent = SAYS.used + ' ' + row.uses_count;
                top.appendChild(used);
            }

            line.appendChild(top);

            // The ingredient under the brand: this is what a doctor was taught
            // to think in, and it is how two near-identical names are told apart.
            if (row.scientific_name || row.manufacturer || row.price_egp !== null) {
                const under = document.createElement('div');
                under.className = 'a2-muted';
                under.style.fontSize = '12px';
                under.style.marginTop = '2px';

                if (row.scientific_name) {
                    under.appendChild(mark(row.scientific_name));
                }

                const tail = [row.manufacturer, row.price_egp !== null ? row.price_egp + ' ج.م' : null]
                    .filter(Boolean).join('  ·  ');

                if (tail) {
                    under.append((row.scientific_name ? '  ·  ' : '') + tail);
                }

                line.appendChild(under);
            }

            out.appendChild(line);
        });
    }

    function look() {
        const term = box.value.trim();

        if (term.length < 2) {
            out.innerHTML = '';
            meta.textContent = term.length ? SAYS.typing : '';

            return;
        }

        // Every keystroke is a request, and they come back out of order — the
        // slow answer to «AUG» would otherwise overwrite the one for «AUGMENTIN».
        const mine = ++seq;
        const started = performance.now();

        fetch(ENDPOINT + '?q=' + encodeURIComponent(term), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (response) { return response.json(); })
            .then(function (body) {
                if (mine !== seq) {
                    return;
                }

                const rows = (body && body.data) || [];
                meta.textContent = rows.length
                    ? (rows.length + ' / ' + (body.total || '?') + ' · ' + Math.round(performance.now() - started) + 'ms')
                    : '';

                render(term, rows);
            })
            .catch(function () {
                if (mine === seq) {
                    meta.textContent = SAYS.failed;
                }
            });
    }

    box.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(look, 180);
    });
});
</script>
@endsection
