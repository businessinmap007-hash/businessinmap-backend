@extends('business.layouts.master')

@section('title', __('روشتاتي'))

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">{{ __('روشتاتي') }}</h1>
        <div class="a2-page-subtitle">{{ __('اكتب روشتة من قاموس الأدوية (بالاسم التجاري أو المادة الفعالة)، وراجع ما أصدرته، واحتفظ بنسخة مشفرة على جهازك.') }}</div>
    </div>
    <div class="a2-page-actions">
        <button type="button" class="a2-btn a2-btn-primary" id="newRxBtn">{{ __('روشتة جديدة') }}</button>
    </div>
</div>

<div id="rxMessage" class="a2-alert" style="display:none"></div>

{{-- ── كتابة روشتة ─────────────────────────────────────────────────────── --}}
<div class="a2-card a2-mb-16" id="writeCard" style="display:none">
    <h3 style="margin-top:0" id="writeTitle">{{ __('روشتة جديدة') }}</h3>
    <div id="reviseNote" class="a2-alert" style="display:none">{{ __('التعديل لا يغيّر الروشتة الحالية: تُصدر نسخة جديدة سارية وتُلغى القديمة، وتبقى القديمة في السجل. لو فيها دواء مخدر فالنسخة الجديدة تحتاج صورة جديدة بخط يدك.') }}</div>

    <div class="a2-form-row" id="visitRow">
        <label>{{ __('المريض (من زيارات عيادتك)') }}</label>
        <select class="a2-select" id="rxVisit"></select>
    </div>
    <div class="a2-form-row">
        <label>{{ __('التشخيص') }}</label>
        <input class="a2-input" id="rxDiagnosis" maxlength="255">
    </div>
    <div class="a2-form-row">
        <label>{{ __('حالة المريض') }}</label>
        <input class="a2-input" id="rxCondition" maxlength="500">
    </div>
    <div class="a2-form-row">
        <label>{{ __('ملاحظات') }}</label>
        <input class="a2-input" id="rxNotes" maxlength="2000">
    </div>

    <h4>{{ __('الأدوية') }}</h4>
    <input class="a2-input" id="medSearch" type="text" autocomplete="off" placeholder="{{ __('ابحث بالاسم التجاري أو المادة الفعالة') }}">
    <div id="medResults" class="a2-table-wrap" style="display:none;max-height:260px;overflow:auto"></div>
    <div id="rxLines" style="margin-top:12px"></div>

    <div id="paperBox" class="a2-alert a2-alert-danger" style="display:none;margin-top:12px">
        <strong>{{ __('في الروشتة دواء مخدر') }}</strong>
        <div>{{ __('منعًا للاحتيال أرفق صورة الروشتة المكتوبة بخط يدك على الورق. الصيدلية تقارنها بالورقة التي مع المريض.') }}</div>
        <input type="file" id="paperFile" accept="image/*" capture="environment" style="margin-top:8px">
    </div>

    <div class="a2-page-actions" style="justify-content:flex-start;gap:8px;margin-top:12px">
        <button type="button" class="a2-btn a2-btn-primary" id="issueBtn">{{ __('إصدار الروشتة') }}</button>
        <button type="button" class="a2-btn a2-btn-ghost" id="cancelWriteBtn">{{ __('إلغاء') }}</button>
    </div>
</div>

{{-- ── ما أصدرته ───────────────────────────────────────────────────────── --}}
<div class="a2-card a2-mb-16">
    <h3 style="margin-top:0">{{ __('ما أصدرته') }}</h3>
    <p class="a2-page-subtitle">{{ __('بعد 90 يومًا من انتهاء الروشتة يحذف السيرفر التشخيص والملاحظات منها (تبقى الأصناف)، لكن فقط بعد أن يحتفظ المريض والطبيب كلٌّ بنسخته. نسختك هنا: مخزنة مشفرة في متصفحك، وتصير «معتمدة» عند تنزيل ملف النسخة المشفرة بكلمة سر تختارها — احتفظ بالملف في مكان آمن.') }}</p>
    <div class="a2-page-actions" style="justify-content:flex-start;gap:8px;flex-wrap:wrap">
        <button type="button" class="a2-btn a2-btn-primary" id="exportBtn">{{ __('تنزيل نسخة مشفرة وتأكيدها') }}</button>
        <label class="a2-btn a2-btn-ghost" style="cursor:pointer;margin:0">{{ __('استعادة من ملف') }}
            <input type="file" id="restoreFile" accept=".bimmed,.json,application/json" style="display:none">
        </label>
    </div>
    <div class="a2-table-wrap" style="margin-top:12px">
        <table class="a2-table">
            <thead><tr><th>#</th><th>{{ __('المريض') }}</th><th>{{ __('الأدوية') }}</th><th>{{ __('الحالة') }}</th><th>{{ __('التاريخ') }}</th><th>{{ __('نسختك') }}</th><th></th></tr></thead>
            <tbody id="rxRows"></tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    // panel_route(): the path with the app base path (never the APP_URL host, which can differ from the served one).
    const URLS = {
        issued: @json(panel_route('business.prescriptions.issued', [])),
        medicines: @json(panel_route('business.prescriptions.medicines', [])),
        appointments: @json(panel_route('business.prescriptions.appointments', [])),
        store: @json(panel_route('business.prescriptions.store', [])),
        archived: @json(panel_route('business.prescriptions.archived', [])),
        revise: @json(panel_route('business.prescriptions.revise', ['id' => '__ID__'])),
    };
    const CSRF = @json(csrf_token());
    const ME = @json((int) auth()->id());
    const T = {
        failed: @json(__('حدث خطأ ما، حاول مرة أخرى.')),
        pickVisit: @json(__('— اختر زيارة —')),
        noVisits: @json(__('لا توجد زيارات حديثة في عيادتك — يلزم موعد للمريض أولًا.')),
        controlled: @json(__('مخدر')),
        dosage: @json(__('الجرعة')), quantity: @json(__('الكمية')), instructions: @json(__('تعليمات')),
        perDay: @json(__('مرات/يوم')), food: @json(__('مع الأكل')), before: @json(__('قبل الأكل')), with: @json(__('مع الأكل')), after: @json(__('بعد الأكل')),
        duration: @json(__('المدة')), days: @json(__('أيام')), weeks: @json(__('أسابيع')), months: @json(__('شهور')),
        remove: @json(__('حذف')),
        needPatient: @json(__('اختر الزيارة (المريض) أولًا.')),
        needLine: @json(__('أضف دواءً واحدًا على الأقل.')),
        needPaper: @json(__('أضف صورة الروشتة المكتوبة بخط اليد أولًا.')),
        issuedOk: @json(__('تم إصدار الروشتة.')),
        revisedOk: @json(__('تم تعديل الروشتة — أصبحت النسخة الجديدة سارية.')),
        edit: @json(__('تعديل')), reviseTitle: @json(__('تعديل الروشتة')), newTitle: @json(__('روشتة جديدة')),
        saveRevise: @json(__('حفظ التعديل')), issue: @json(__('إصدار الروشتة')),
        legacyLines: @json(__('بعض الأسطر قديمة وغير مربوطة بالقاموس، لذلك لم تُنقل — أضفها من القاموس إن لزمت.')),
        held: @json(__('✓ معتمدة')), local: @json(__('على هذا الجهاز')), none: @json(__('—')),
        purged: @json(__('(حُذف التشخيص من السيرفر — النسخة هنا)')),
        passphrase: @json(__('اختر كلمة سر للنسخة (8 أحرف على الأقل). لن تُحفظ في أي مكان — بدونها لا تُفتح النسخة.')),
        shortPass: @json(__('كلمة السر أقصر من 8 أحرف.')),
        exported: @json(__('تم تنزيل النسخة المشفرة واعتماد :n روشتة.')),
        restoredN: @json(__('تمت استعادة :n روشتة من الملف.')),
        wrongPass: @json(__('كلمة السر غير صحيحة أو الملف تالف.')),
        nothing: @json(__('لا توجد روشتات لتنزيلها.')),
        status: { issued: @json(__('صادرة')), sent_to_pharmacy: @json(__('أُرسلت لصيدلية')), preparing: @json(__('قيد التجهيز')), ready: @json(__('جاهزة')), dispensed: @json(__('صُرفت')), cancelled: @json(__('ملغاة')) },
    };

    const $ = id => document.getElementById(id);
    function say(text, kind) {
        const box = $('rxMessage');
        box.textContent = text;
        box.className = 'a2-alert ' + (kind === 'ok' ? 'a2-alert-success' : 'a2-alert-danger');
        box.style.display = text ? 'block' : 'none';
        if (text) box.scrollIntoView({ block: 'nearest' });
    }

    async function api(url, options) {
        const res = await fetch(url, Object.assign({ credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF } }, options));
        const body = await res.json().catch(() => ({}));
        if (!res.ok) {
            const first = body.errors ? Object.values(body.errors)[0][0] : null;
            const err = new Error(first || body.message || T.failed);
            err.body = body;
            throw err;
        }
        return body;
    }
    const post = (url, json) => api(url, { method: 'POST', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF }, body: JSON.stringify(json) });

    // ══ the copy kept on this computer ═════════════════════════════════════════════════════════════════════════
    // IndexedDB holds each prescription AES-GCM-encrypted under a key the browser cannot export (a non-extractable
    // CryptoKey stored beside it): a stolen profile folder does not reveal the records. The file the doctor
    // downloads is the durable copy — the same envelope the phone's backup uses (PBKDF2-SHA256 310k, AES-256-GCM).
    const Local = (() => {
        const open = () => new Promise((resolve, reject) => {
            const req = indexedDB.open('bim_doctor_rx', 1);
            req.onupgradeneeded = () => { req.result.createObjectStore('keys'); req.result.createObjectStore('rx'); };
            req.onsuccess = () => resolve(req.result);
            req.onerror = () => reject(req.error);
        });
        const tx = async (store, mode, fn) => {
            const db = await open();
            return new Promise((resolve, reject) => {
                const t = db.transaction(store, mode);
                const out = fn(t.objectStore(store));
                t.oncomplete = () => resolve(out && 'result' in out ? out.result : undefined);
                t.onerror = () => reject(t.error);
            });
        };
        async function key() {
            let k = await tx('keys', 'readonly', s => s.get('k'));
            if (!k) {
                k = await crypto.subtle.generateKey({ name: 'AES-GCM', length: 256 }, false, ['encrypt', 'decrypt']);
                await tx('keys', 'readwrite', s => s.put(k, 'k'));
            }
            return k;
        }
        const enc = new TextEncoder(), dec = new TextDecoder();
        return {
            async put(raw) {
                const iv = crypto.getRandomValues(new Uint8Array(12));
                const data = await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, await key(), enc.encode(JSON.stringify(raw)));
                await tx('rx', 'readwrite', s => s.put({ iv, data }, ME + ':' + raw.id));
            },
            async all() {
                const k = await key();
                const db = await open();
                const rows = await new Promise((resolve, reject) => {
                    const out = [];
                    const req = db.transaction('rx', 'readonly').objectStore('rx').openCursor();
                    req.onsuccess = () => { const c = req.result; if (c) { if (String(c.key).startsWith(ME + ':')) out.push(c.value); c.continue(); } else resolve(out); };
                    req.onerror = () => reject(req.error);
                });
                const list = [];
                for (const r of rows) {
                    try { list.push(JSON.parse(dec.decode(await crypto.subtle.decrypt({ name: 'AES-GCM', iv: r.iv }, k, r.data)))); } catch (e) { /* a record from another key */ }
                }
                return list;
            },
        };
    })();

    // The backup envelope — byte-for-byte what the phone's MedicalBackupCrypto reads and writes.
    const Backup = (() => {
        const ITER = 310000;
        const b64 = bytes => btoa(String.fromCharCode(...new Uint8Array(bytes)));
        const unb64 = text => Uint8Array.from(atob(text), c => c.charCodeAt(0));
        const derive = async (pass, salt, iter) => {
            const base = await crypto.subtle.importKey('raw', new TextEncoder().encode(pass), 'PBKDF2', false, ['deriveKey']);
            return crypto.subtle.deriveKey({ name: 'PBKDF2', hash: 'SHA-256', salt, iterations: iter }, base, { name: 'AES-GCM', length: 256 }, false, ['encrypt', 'decrypt']);
        };
        return {
            async seal(prescriptions, pass) {
                const salt = crypto.getRandomValues(new Uint8Array(16));
                const iv = crypto.getRandomValues(new Uint8Array(12));
                const content = JSON.stringify({ v: 2, file: {}, prescriptions });
                const ct = new Uint8Array(await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, await derive(pass, salt, ITER), new TextEncoder().encode(content)));
                const data = new Uint8Array(iv.length + ct.length);
                data.set(iv); data.set(ct, iv.length);   // nonce || ciphertext || tag
                return JSON.stringify({ v: 1, kdf: 'pbkdf2-sha256', iterations: ITER, salt: b64(salt), data: b64(data) });
            },
            async open(blob, pass) {
                const j = JSON.parse(blob);
                const bytes = unb64(j.data);
                const clear = await crypto.subtle.decrypt({ name: 'AES-GCM', iv: bytes.slice(0, 12) }, await derive(pass, unb64(j.salt), j.iterations), bytes.slice(12));
                const content = JSON.parse(new TextDecoder().decode(clear));
                return content.v === 2 ? (content.prescriptions || []) : [];
            },
        };
    })();
    window.__bimBackup = Backup; // for the interop check in the test-suite

    // ══ what the server sent, merged with what this computer keeps ═══════════════════════════════════════════
    let server = [];   // issued prescriptions as the server sent them
    let local = {};    // id → the raw copy kept here
    const SENSITIVE = ['diagnosis', 'patient_condition', 'notes', 'verifiable'];

    // A purged server copy must never replace the full one kept here.
    function merged(fresh, old) {
        if (!fresh.content_purged || !old) return fresh;
        const out = Object.assign({}, fresh);
        SENSITIVE.forEach(k => { if (old[k] != null) out[k] = old[k]; });
        return out;
    }

    async function loadIssued() {
        server = [];
        for (let page = 1; page < 50; page++) {
            const body = await api(URLS.issued + '?per_page=50&page=' + page);
            server.push(...body.data.data);
            if (!body.data.next_page_url) break;
        }
        const have = await Local.all();
        local = {};
        have.forEach(r => { local[r.id] = r; });
        for (const p of server) {
            local[p.id] = merged(p, local[p.id]);
            await Local.put(local[p.id]);
        }
        render();
    }

    function patientName(p) { return (p.patient && p.patient.name) || ('#' + (p.patient && p.patient.id)); }

    function render() {
        const tbody = $('rxRows');
        tbody.innerHTML = '';
        Object.values(local).sort((a, b) => b.id - a.id).forEach(p => {
            const tr = document.createElement('tr');
            const names = (p.items || []).map(i => i.name + (i.is_controlled ? ' ⚠' : '')).join('، ');
            const cells = [
                p.id, patientName(p),
                names + (p.content_purged ? ' ' + T.purged : ''),
                T.status[p.status] || p.status,
                p.issued_at ? new Date(p.issued_at).toLocaleDateString() : '',
                (p.archived_by_doctor ? T.held + ' · ' : '') + T.local,
            ];
            cells.forEach(text => { const td = document.createElement('td'); td.textContent = text; tr.appendChild(td); });
            const act = document.createElement('td');
            // an amendment is for what is still alive: not a dispensed or a cancelled one
            if (p.status !== 'dispensed' && p.status !== 'cancelled' && p.doctor && p.doctor.id === ME) {
                const b = document.createElement('button');
                b.type = 'button'; b.className = 'a2-btn a2-btn-ghost'; b.textContent = T.edit;
                b.addEventListener('click', () => startRevise(p));
                act.appendChild(b);
            }
            tr.appendChild(act);
            tbody.appendChild(tr);
        });
    }

    // ── download the encrypted copy, then tell the server this computer holds it ──
    $('exportBtn').addEventListener('click', async () => {
        say('');
        try {
            const all = Object.values(local);
            if (!all.length) { say(T.nothing); return; }
            const pass = window.prompt(T.passphrase);
            if (pass === null) return;
            if (pass.length < 8) { say(T.shortPass); return; }
            const blob = await Backup.seal(all, pass);
            const url = URL.createObjectURL(new Blob([blob], { type: 'application/json' }));
            const a = document.createElement('a');
            a.href = url; a.download = 'prescriptions-' + new Date().toISOString().slice(0, 10) + '.bimmed';
            document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);

            // The file exists: confirm each full copy to the server (it checks the fingerprint, stores nothing).
            let n = 0;
            for (const p of all) {
                if (p.verifiable && p.verifiable.content && !p.archived_by_doctor) {
                    try { await post(URLS.archived, { id: p.id, content: p.verifiable.content }); p.archived_by_doctor = true; await Local.put(p); n++; } catch (e) { /* tried again next time */ }
                }
            }
            render();
            say(T.exported.replace(':n', n), 'ok');
        } catch (e) { say(e.message || T.failed); }
    });

    $('restoreFile').addEventListener('change', async ev => {
        say('');
        const file = ev.target.files[0];
        ev.target.value = '';
        if (!file) return;
        try {
            const pass = window.prompt(T.passphrase.split(' (')[0]);
            if (pass === null) return;
            let rows;
            try { rows = await Backup.open(await file.text(), pass); } catch (e) { say(T.wrongPass); return; }
            let n = 0;
            for (const r of rows) {
                if (r && r.id && !local[r.id]) { local[r.id] = r; await Local.put(r); n++; }
            }
            render();
            say(T.restoredN.replace(':n', n), 'ok');
        } catch (e) { say(e.message || T.failed); }
    });

    // ══ writing a prescription ═══════════════════════════════════════════════════════════════════════════════
    let lines = [];
    let visits = [];

    let reviseOf = null;   // the prescription being amended, or null when writing a new one

    function openWriter(title) {
        $('writeTitle').textContent = title;
        $('issueBtn').textContent = reviseOf ? T.saveRevise : T.issue;
        $('visitRow').style.display = reviseOf ? 'none' : '';
        $('reviseNote').style.display = reviseOf ? 'block' : 'none';
        $('writeCard').style.display = 'block';
        $('writeCard').scrollIntoView({ block: 'start' });
    }

    $('newRxBtn').addEventListener('click', async () => {
        reviseOf = null;
        lines = []; renderLines();
        ['rxDiagnosis', 'rxCondition', 'rxNotes'].forEach(id => { $(id).value = ''; });
        $('paperFile').value = '';
        openWriter(T.newTitle);
        try {
            visits = (await api(URLS.appointments)).data;
            const sel = $('rxVisit');
            sel.innerHTML = '';
            sel.add(new Option(visits.length ? T.pickVisit : T.noVisits, ''));
            visits.forEach((v, i) => sel.add(new Option(v.patient_name + (v.scheduled_at ? ' — ' + new Date(v.scheduled_at).toLocaleString() : ''), String(i))));
        } catch (e) { say(e.message || T.failed); }
    });

    // Amend: the form opens filled from the copy kept here (the full one, even when the server purged its own).
    function startRevise(p) {
        say('');
        reviseOf = p.id;
        $('rxDiagnosis').value = p.diagnosis || '';
        $('rxCondition').value = p.patient_condition || '';
        $('rxNotes').value = p.notes || '';
        $('paperFile').value = '';
        const items = p.items || [];
        lines = items.filter(i => i.medicine_id).map(i => ({
            medicine_id: i.medicine_id, name: i.name, is_controlled: !!i.is_controlled,
            dosage: i.dosage || '', quantity: i.quantity || '', instructions: i.instructions || '',
            frequency_per_day: i.frequency_per_day || '', food_timing: i.food_timing || '',
            duration_value: i.duration_value || '', duration_unit: i.duration_unit || 'days',
            time_slots: i.time_slots || null,
        }));
        renderLines();
        openWriter(T.reviseTitle + ' #' + p.id + ' — ' + patientName(p));
        if (lines.length < items.length) say(T.legacyLines);
    }

    $('cancelWriteBtn').addEventListener('click', () => { $('writeCard').style.display = 'none'; });

    let searchTimer = null;
    $('medSearch').addEventListener('input', () => {
        clearTimeout(searchTimer);
        const q = $('medSearch').value.trim();
        const box = $('medResults');
        if (!q) { box.style.display = 'none'; return; }
        searchTimer = setTimeout(async () => {
            try {
                const rows = (await api(URLS.medicines + '?limit=20&q=' + encodeURIComponent(q))).data;
                box.innerHTML = '';
                const table = document.createElement('table');
                table.className = 'a2-table';
                rows.forEach(m => {
                    const tr = document.createElement('tr');
                    tr.style.cursor = 'pointer';
                    const td = document.createElement('td');
                    td.textContent = (m.is_controlled ? '⚠ ' + T.controlled + ' · ' : '') + m.name + (m.strength ? ' — ' + m.strength : '')
                        + (m.scientific_name ? ' · ' + m.scientific_name : '');
                    tr.appendChild(td);
                    tr.addEventListener('click', () => { addLine(m); box.style.display = 'none'; $('medSearch').value = ''; });
                    table.appendChild(tr);
                });
                box.appendChild(table);
                box.style.display = rows.length ? 'block' : 'none';
            } catch (e) { say(e.message || T.failed); }
        }, 250);
    });

    function addLine(m) {
        lines.push({ medicine_id: m.id, name: m.name + (m.strength ? ' — ' + m.strength : ''), is_controlled: !!m.is_controlled, dosage: '', quantity: '', instructions: '', frequency_per_day: '', food_timing: '', duration_value: '', duration_unit: 'days' });
        renderLines();
    }

    function renderLines() {
        const box = $('rxLines');
        box.innerHTML = '';
        lines.forEach((l, i) => {
            const card = document.createElement('div');
            card.className = 'a2-card a2-card--tight';
            card.style.marginBottom = '8px';
            const title = document.createElement('strong');
            title.textContent = (l.is_controlled ? '⚠ ' + T.controlled + ' · ' : '') + l.name;
            card.appendChild(title);
            const grid = document.createElement('div');
            grid.style.cssText = 'display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px;margin-top:8px';
            const field = (key, label, type, extra) => {
                const wrap = document.createElement('label');
                wrap.textContent = label;
                wrap.style.fontSize = '12px';
                const el = document.createElement(type === 'select' ? 'select' : 'input');
                el.className = type === 'select' ? 'a2-select' : 'a2-input';
                if (type === 'select') { extra.forEach(([v, t]) => el.add(new Option(t, v))); }
                else { el.type = type; if (extra) Object.assign(el, extra); }
                el.value = l[key];
                el.addEventListener('input', () => { l[key] = el.value; });
                el.addEventListener('change', () => { l[key] = el.value; });
                wrap.appendChild(el);
                grid.appendChild(wrap);
            };
            field('dosage', T.dosage, 'text', { maxLength: 120 });
            field('quantity', T.quantity, 'text', { maxLength: 120 });
            field('instructions', T.instructions, 'text', { maxLength: 255 });
            field('frequency_per_day', T.perDay, 'number', { min: 1, max: 12 });
            field('food_timing', T.food, 'select', [['', T.none], ['before', T.before], ['with', T.with], ['after', T.after]]);
            field('duration_value', T.duration, 'number', { min: 1, max: 60 });
            field('duration_unit', '', 'select', [['days', T.days], ['weeks', T.weeks], ['months', T.months]]);
            card.appendChild(grid);
            const rm = document.createElement('button');
            rm.type = 'button'; rm.className = 'a2-btn a2-btn-ghost'; rm.textContent = T.remove; rm.style.marginTop = '8px';
            rm.addEventListener('click', () => { lines.splice(i, 1); renderLines(); });
            card.appendChild(rm);
            box.appendChild(card);
        });
        $('paperBox').style.display = lines.some(l => l.is_controlled) ? 'block' : 'none';
    }

    $('issueBtn').addEventListener('click', async () => {
        say('');
        const visit = visits[parseInt($('rxVisit').value, 10)];
        if (!reviseOf && !visit) { say(T.needPatient); return; }
        if (!lines.length) { say(T.needLine); return; }
        const paper = $('paperFile').files[0];
        if (lines.some(l => l.is_controlled) && !paper) { say(T.needPaper); return; }

        const items = lines.map(l => {
            const o = { medicine_id: l.medicine_id };
            ['dosage', 'quantity', 'instructions', 'food_timing'].forEach(k => { if (l[k]) o[k] = l[k]; });
            if (l.frequency_per_day) o.frequency_per_day = parseInt(l.frequency_per_day, 10);
            if (l.duration_value) { o.duration_value = parseInt(l.duration_value, 10); o.duration_unit = l.duration_unit; }
            if (Array.isArray(l.time_slots) && l.time_slots.length) o.time_slots = l.time_slots;
            return o;
        });
        const form = new FormData();
        if (!reviseOf) { form.append('patient_id', visit.patient_id); form.append('appointment_id', visit.id); }
        [['diagnosis', 'rxDiagnosis'], ['patient_condition', 'rxCondition'], ['notes', 'rxNotes']].forEach(([k, id]) => { if ($(id).value.trim()) form.append(k, $(id).value.trim()); });
        form.append('items', JSON.stringify(items));
        if (paper) { form.append('handwritten_image', paper); form.append('handwritten_source', 'upload'); }

        $('issueBtn').disabled = true;
        try {
            const revising = reviseOf;
            await api(revising ? URLS.revise.replace('__ID__', revising) : URLS.store, { method: 'POST', body: form });
            reviseOf = null;
            lines = []; renderLines();
            ['rxDiagnosis', 'rxCondition', 'rxNotes'].forEach(id => { $(id).value = ''; });
            $('paperFile').value = '';
            $('writeCard').style.display = 'none';
            say(revising ? T.revisedOk : T.issuedOk, 'ok');
            await loadIssued();
        } catch (e) { say(e.message || T.failed); }
        finally { $('issueBtn').disabled = false; }
    });

    loadIssued().catch(e => say(e.message || T.failed));
})();
</script>
@endpush
