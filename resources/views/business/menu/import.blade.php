@extends('business.layouts.master')

@section('title', __('استيراد وتصدير المنيو'))

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">{{ __('استيراد وتصدير المنيو') }}</h1>
        <div class="a2-page-subtitle">{{ __('نزّل أصنافك في ملف Excel، عدّلها أو أضف عليها، ثم ارفع الملف. ترى معاينة بما سيحدث قبل أي تغيير.') }}</div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('business.menu.index') }}" class="a2-btn a2-btn-ghost">{{ __('رجوع') }}</a>
    </div>
</div>

<div class="a2-card a2-mb-16">
    <h3 style="margin-top:0">{{ __('تصدير') }}</h3>
    <p class="a2-page-subtitle">{{ __('الملف فيه رقم كل صنف: لو عدّلته ورفعته يتحدث الصنف نفسه. صنف بلا رقم يُضاف جديدًا (أو يتحدث لو عندك صنف بنفس الاسم).') }}</p>
    <div class="a2-page-actions" style="justify-content:flex-start;flex-wrap:wrap;gap:8px">
        <button type="button" class="a2-btn a2-btn-primary" data-export="0">{{ __('تحميل أصنافي (Excel)') }}</button>
        <button type="button" class="a2-btn a2-btn-ghost" data-export="1">{{ __('نموذج فارغ (Excel)') }}</button>
        <a class="a2-btn a2-btn-ghost" href="{{ panel_route('business.menu.sheet.csv', []) }}">{{ __('تحميل أصنافي (CSV)') }}</a>
    </div>
</div>

<div class="a2-card a2-mb-16">
    <h3 style="margin-top:0">{{ __('استيراد') }}</h3>
    <p class="a2-page-subtitle">{{ __('ملف Excel أو CSV. بعد اختياره ترى أعمدة ملفك مرقّمة وتربط كل عمود عندنا برقم العمود المقابل له — فلا يهم اختلاف الأسماء. «النوع» من أنواع نشاطك (في الورقة الثانية من النموذج)، و«القسم» للأصناف التي ليس لها نوع. المقاسات والإضافات والصور والباركود اختيارية — طريقة كتابتها في الورقة الثانية.') }}</p>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <input type="file" id="sheetFile" accept=".xlsx,.xls,.csv" class="a2-input" style="max-width:360px">
    </div>
    <div id="sheetError" class="a2-alert a2-alert-danger" style="display:none;margin-top:12px"></div>
</div>

<div class="a2-card a2-mb-16" id="mappingCard" style="display:none">
    <h3 style="margin-top:0">{{ __('ربط الأعمدة') }}</h3>
    <p class="a2-page-subtitle">{{ __('لكل عمود عندنا اختر رقم العمود المقابل له في ملفك. ما تتركه «بدون» لا يُقرأ، ولا يغيّر شيئًا في صنف موجود.') }}</p>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px">
        <div>
            <strong>{{ __('أعمدة ملفك') }}</strong>
            <ol id="fileHeaders" style="margin:8px 0 0;padding:0;list-style:none"></ol>
        </div>
        <div style="grid-column:span 2">
            <strong>{{ __('أعمدة التطبيق') }}</strong>
            <div class="a2-table-wrap">
                <table class="a2-table">
                    <thead><tr><th>#</th><th>{{ __('العمود عندنا') }}</th><th>{{ __('يقابله في ملفك') }}</th><th>{{ __('مثال من ملفك') }}</th></tr></thead>
                    <tbody id="mappingRows"></tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="a2-page-actions" style="justify-content:flex-start;gap:8px;margin-top:12px">
        <button type="button" class="a2-btn a2-btn-primary" id="previewBtn">{{ __('معاينة بهذا الربط') }}</button>
        <button type="button" class="a2-btn a2-btn-ghost" id="resetMapBtn">{{ __('إعادة الربط التلقائي') }}</button>
    </div>
</div>

<div class="a2-card" id="reportCard" style="display:none">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap">
        <h3 style="margin:0" id="reportTitle"></h3>
        <button type="button" class="a2-btn a2-btn-primary" id="confirmBtn">{{ __('تأكيد الاستيراد') }}</button>
    </div>
    <p id="reportSummary" class="a2-page-subtitle"></p>
    <div class="a2-table-wrap">
        <table class="a2-table">
            <thead><tr><th>{{ __('الصف') }}</th><th>{{ __('الاسم') }}</th><th>{{ __('الإجراء') }}</th><th>{{ __('ملاحظات') }}</th></tr></thead>
            <tbody id="reportRows"></tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
(function () {
    // panel_route(): the path with the app base path (never the APP_URL host, which can differ from the served one).
    const SHEET_URL = @json(panel_route('business.menu.sheet', []));
    const IMPORT_URL = @json(panel_route('business.menu.import.run', []));
    const INSPECT_URL = @json(panel_route('business.menu.inspect', []));
    const CSRF = @json(csrf_token());
    const T = {
        preview: @json(__('معاينة — لم يتغير شيء بعد')),
        done: @json(__('تم الاستيراد')),
        summary: @json(__('جديد: :c — تحديث: :u — أخطاء: :e')),
        create: @json(__('جديد')), update: @json(__('تحديث')), error: @json(__('خطأ')),
        empty: @json(__('الملف فارغ أو بلا صفوف مقروءة.')),
        failed: @json(__('حدث خطأ ما، حاول مرة أخرى.')),
        typesSheet: @json(__('الأنواع والوحدات')), types: @json(__('النوع')), group: @json(__('المجموعة')), units: @json(__('الوحدات')),
        howTo: @json(__('طريقة الكتابة')),
        none: @json(__('— بدون —')),
        column: @json(__('عمود')),
    };
    let grid = null, inspected = null, mapping = {};

    const fileInput = document.getElementById('sheetFile');
    const previewBtn = document.getElementById('previewBtn');
    const confirmBtn = document.getElementById('confirmBtn');
    const errorBox = document.getElementById('sheetError');
    const mappingCard = document.getElementById('mappingCard');

    function showError(text) { errorBox.textContent = text; errorBox.style.display = text ? 'block' : 'none'; }

    async function post(url, payload) {
        const res = await fetch(url, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify(payload),
        });
        const body = await res.json();
        if (!res.ok) throw new Error(body.message || T.failed);
        return body.data;
    }

    async function getJson(url) {
        const res = await fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
        if (!res.ok) throw new Error(T.failed);
        return (await res.json()).data;
    }

    // ── export: the sheet, plus a second sheet listing what «النوع» and «الوحدة» may say ──
    document.querySelectorAll('[data-export]').forEach(btn => btn.addEventListener('click', async () => {
        try {
            const template = btn.dataset.export === '1';
            const data = await getJson(SHEET_URL + (template ? '?template=1' : ''));
            const header = data.columns.map(c => c.label);
            const body = data.rows.map(r => data.columns.map(c => r[c.key] ?? ''));
            const book = XLSX.utils.book_new();
            const sheet = XLSX.utils.aoa_to_sheet([header, ...body]);
            sheet['!cols'] = header.map(() => ({ wch: 18 }));
            XLSX.utils.book_append_sheet(book, sheet, 'menu');
            const help = data.vocabulary.help || [];
            const lists = [[T.types, T.group, T.units, T.howTo]];
            const n = Math.max(data.vocabulary.lines.length, data.vocabulary.units.length, help.length);
            for (let i = 0; i < n; i++) {
                const l = data.vocabulary.lines[i];
                lists.push([l ? l.name : '', l ? l.group : '', data.vocabulary.units[i] ?? '', help[i] ?? '']);
            }
            XLSX.utils.book_append_sheet(book, XLSX.utils.aoa_to_sheet(lists), T.typesSheet.substring(0, 31));
            XLSX.writeFile(book, template ? 'menu-template.xlsx' : 'menu.xlsx');
        } catch (e) { showError(e.message || T.failed); }
    }));

    // ── import: read the first sheet in the browser as a grid, let the owner point our columns at his, send both ──
    // The same file shape comes back to the same mapping: remembered in this browser.
    const memoryKey = headers => 'bim_menu_import_map_' + headers.join('|');
    function remembered(headers) {
        try { return JSON.parse(localStorage.getItem(memoryKey(headers)) || 'null'); } catch (e) { return null; }
    }
    function remember() {
        try { localStorage.setItem(memoryKey(inspected.headers), JSON.stringify(mapping)); } catch (e) { /* private window */ }
    }
    const headerLabel = (h, i) => (i + 1) + ' - ' + (h || T.column + ' ' + (i + 1));

    fileInput.addEventListener('change', async () => {
        showError('');
        grid = inspected = null;
        mappingCard.style.display = 'none';
        document.getElementById('reportCard').style.display = 'none';
        const file = fileInput.files[0];
        if (!file) return;
        try {
            const book = XLSX.read(await file.arrayBuffer(), { type: 'array', codepage: 65001 });
            const first = book.Sheets[book.SheetNames[0]];
            grid = XLSX.utils.sheet_to_json(first, { header: 1, defval: '', raw: false, blankrows: false });
            if (grid.length < 2) { grid = null; showError(T.empty); return; }
            inspected = await post(INSPECT_URL, { grid });
            mapping = remembered(inspected.headers) || { ...inspected.mapping };
            renderMapping();
        } catch (e) { showError(e.message || T.failed); }
    });

    function renderMapping() {
        mappingCard.style.display = 'block';
        const list = document.getElementById('fileHeaders');
        list.innerHTML = '';
        inspected.headers.forEach((h, i) => {
            const li = document.createElement('li');
            li.style.padding = '3px 0';
            li.textContent = headerLabel(h, i);
            list.appendChild(li);
        });
        const tbody = document.getElementById('mappingRows');
        tbody.innerHTML = '';
        inspected.columns.forEach((col, n) => {
            const tr = document.createElement('tr');
            const select = document.createElement('select');
            select.className = 'a2-input';
            select.add(new Option(T.none, ''));
            inspected.headers.forEach((h, i) => select.add(new Option(headerLabel(h, i), String(i + 1))));
            select.value = mapping[col.key] ? String(mapping[col.key]) : '';
            const sample = document.createElement('td');
            sample.style.color = '#777';
            const refresh = () => {
                const idx = select.value ? parseInt(select.value, 10) - 1 : -1;
                sample.textContent = idx >= 0 ? ((inspected.sample[0] || [])[idx] ?? '') : '';
            };
            select.addEventListener('change', () => { mapping[col.key] = select.value ? parseInt(select.value, 10) : null; refresh(); });
            refresh();
            [String(n + 1), col.label].forEach(text => { const td = document.createElement('td'); td.textContent = text; tr.appendChild(td); });
            const tdSel = document.createElement('td');
            tdSel.appendChild(select);
            tr.appendChild(tdSel);
            tr.appendChild(sample);
            tbody.appendChild(tr);
        });
    }

    document.getElementById('resetMapBtn').addEventListener('click', () => { mapping = { ...inspected.mapping }; renderMapping(); });

    previewBtn.addEventListener('click', async () => {
        showError('');
        if (!grid) return;
        remember();
        await run(true);
    });

    confirmBtn.addEventListener('click', () => run(false));

    async function run(dryRun) {
        previewBtn.disabled = confirmBtn.disabled = true;
        try {
            render(await post(IMPORT_URL, { grid, mapping, dry_run: dryRun ? 1 : 0 }), dryRun);
        } catch (e) { showError(e.message || T.failed); }
        finally { previewBtn.disabled = false; confirmBtn.disabled = false; }
    }

    function render(report, dryRun) {
        document.getElementById('reportCard').style.display = 'block';
        document.getElementById('reportTitle').textContent = dryRun ? T.preview : T.done;
        document.getElementById('reportSummary').textContent = T.summary
            .replace(':c', report.summary.create).replace(':u', report.summary.update).replace(':e', report.summary.error);
        confirmBtn.style.display = dryRun && (report.summary.create + report.summary.update) > 0 ? '' : 'none';
        const colors = { create: '#1e8e3e', update: '#1a73e8', error: '#d93025' };
        const tbody = document.getElementById('reportRows');
        tbody.innerHTML = '';
        report.rows.forEach(r => {
            const tr = document.createElement('tr');
            [r.row, r.name, T[r.action] || r.action, [...(r.errors || []), ...(r.warnings || [])].join(' — ')].forEach((text, i) => {
                const td = document.createElement('td');
                td.textContent = text;
                if (i === 2) { td.style.color = colors[r.action] || ''; td.style.fontWeight = '600'; }
                tr.appendChild(td);
            });
            tbody.appendChild(tr);
        });
    }
})();
</script>
@endpush
