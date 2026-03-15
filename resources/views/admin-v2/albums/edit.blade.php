@extends('admin-v2.layouts.master')

@section('title', 'تعديل ألبوم')
@section('body_class', 'admin-v2-albums-edit')

@section('content')
@php
    $a = $album;
    $imageNow = old('image', (string) ($a->image ?? ''));
    $imgs = $a->images ?? collect();
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">تعديل ألبوم #{{ $a->id }}</h1>
            <div class="a2-page-subtitle" dir="ltr">
                Created: {{ $a->created_at ? $a->created_at->format('Y-m-d H:i') : '—' }}
            </div>
        </div>

        <div class="a2-page-actions">
            <a class="a2-btn a2-btn-ghost" href="{{ route('admin.albums.show', $a->id) }}">
                رجوع
            </a>
        </div>
    </div>

    <div class="a2-card a2-page-narrow">
        @if ($errors->any())
            <div class="a2-alert a2-alert-danger a2-mb-12">
                <div class="a2-fw-900 a2-mb-8">يوجد أخطاء</div>
                <ul class="a2-errors-list">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.albums.update', $a->id) }}" class="a2-form">
            @csrf
            @method('PUT')

            <div class="a2-album-edit-grid">
                {{-- LEFT / COVER --}}
                <div>
                    <div class="a2-card a2-card-flat">
                        <div class="a2-header">
                            <h3 class="a2-section-title">الغلاف</h3>
                        </div>

                        <div class="a2-album-cover-body">
                            <div id="a2AlbumPreview">
                                @if($imageNow)
                                    <div class="a2-album-cover-preview-wrap">
                                        <img
                                            src="{{ asset($imageNow) }}"
                                            alt="cover"
                                            class="a2-album-cover-preview-img"
                                        >
                                    </div>
                                @else
                                    <div class="a2-album-cover-placeholder a2-muted">
                                        اختر صورة
                                    </div>
                                @endif
                            </div>

                            <input type="hidden" name="image" id="a2AlbumImage" value="{{ $imageNow }}">

                            <div class="a2-album-upload-row">
                                <input type="file" id="a2AlbumFile" accept="image/*" class="a2-input a2-album-file-input">
                                <button type="button" class="a2-btn a2-btn-ghost" id="a2AlbumUploadBtn">رفع</button>
                                <button type="button" class="a2-btn a2-btn-ghost" id="a2AlbumClearBtn">مسح</button>
                            </div>

                            <div class="a2-muted a2-mt-8" id="a2AlbumUploadMsg"></div>
                        </div>
                    </div>
                </div>

                {{-- RIGHT / FIELDS --}}
                <div>
                    <div class="a2-form-grid">
                        <div class="a2-form-group">
                            <label class="a2-label">Title AR</label>
                            <input
                                class="a2-input"
                                name="title_ar"
                                value="{{ old('title_ar', (string) ($a->title_ar ?? '')) }}"
                                maxlength="191"
                            >
                        </div>

                        <div class="a2-form-group">
                            <label class="a2-label">Title EN</label>
                            <input
                                class="a2-input"
                                name="title_en"
                                value="{{ old('title_en', (string) ($a->title_en ?? '')) }}"
                                maxlength="191"
                                dir="ltr"
                            >
                        </div>

                        <div class="a2-form-group a2-field-full">
                            <label class="a2-label">Description AR</label>
                            <textarea class="a2-textarea" name="description_ar" rows="4">{{ old('description_ar', (string) ($a->description_ar ?? '')) }}</textarea>
                        </div>

                        <div class="a2-form-group a2-field-full">
                            <label class="a2-label">Description EN</label>
                            <textarea class="a2-textarea" name="description_en" rows="4" dir="ltr">{{ old('description_en', (string) ($a->description_en ?? '')) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="a2-card a2-card-flat a2-mt-12">
                <div class="a2-header">
                    <h3 class="a2-section-title">صور الألبوم</h3>
                    <div class="a2-hint">{{ $imgs->count() }} صورة</div>
                </div>

                <div class="a2-album-gallery-body">
                    @if($imgs->count())
                        <div id="a2AlbumImagesGrid" class="a2-album-images-grid">
                            @foreach($imgs as $img)
                                @php
                                    $p = (string) ($img->path ?? $img->url ?? $img->image ?? '');
                                    $isCover = ($p !== '' && (string) $a->image === $p);

                                    $setCoverUrl = route('admin.albums.images.set-cover', [$a->id, $img->id]);
                                    $deleteUrl = route('admin.albums.images.delete', [$a->id, $img->id]);
                                @endphp

                                <div
                                    class="a2-card a2-card-flat a2-album-img-card"
                                    data-img-id="{{ $img->id }}"
                                    data-img-path="{{ $p }}"
                                    data-set-cover-url="{{ $setCoverUrl }}"
                                    data-delete-url="{{ $deleteUrl }}"
                                >
                                    @if($p !== '')
                                        <button type="button" class="a2-album-img-open">
                                            <img src="{{ asset($p) }}" alt="album-image" class="a2-album-grid-image">
                                        </button>

                                        <div class="a2-muted a2-clip a2-mt-8" dir="ltr" title="{{ $p }}">
                                            {{ $p }}
                                        </div>
                                    @else
                                        <div class="a2-album-grid-placeholder a2-muted">
                                            —
                                        </div>
                                    @endif

                                    <div class="a2-album-img-actions">
                                        <span class="a2-pill {{ $isCover ? 'a2-pill-active' : 'a2-pill-gray' }} a2-cover-badge">
                                            {{ $isCover ? 'Cover' : '—' }}
                                        </span>

                                        <button
                                            type="button"
                                            class="a2-btn a2-btn-ghost a2-btn-sm a2-btn-set-cover"
                                            @disabled($isCover)
                                        >
                                            تعيين كغلاف
                                        </button>

                                        <button type="button" class="a2-btn a2-btn-ghost a2-btn-sm a2-btn-del-img">
                                            حذف
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="a2-muted">لا توجد صور داخل الألبوم.</div>
                    @endif
                </div>
            </div>

            <div class="a2-actionsbar a2-mt-16">
                <button type="submit" class="a2-btn a2-btn-primary">حفظ</button>
                <a class="a2-btn a2-btn-ghost" href="{{ route('admin.albums.show', $a->id) }}">إلغاء</a>
            </div>
        </form>
    </div>
</div>

<script>
(() => {
    const uploadUrl = @json(route('admin.upload.image'));
    const token = @json(csrf_token());

    const el = {
        fileInput: document.getElementById('a2AlbumFile'),
        uploadBtn: document.getElementById('a2AlbumUploadBtn'),
        clearBtn: document.getElementById('a2AlbumClearBtn'),
        msg: document.getElementById('a2AlbumUploadMsg'),
        hidden: document.getElementById('a2AlbumImage'),
        preview: document.getElementById('a2AlbumPreview'),
    };

    const PLACEHOLDER = `
        <div class="a2-album-cover-placeholder a2-muted">
            اختر صورة
        </div>
    `;

    function setMsg(text) {
        if (!el.msg) return;
        el.msg.textContent = text || '';
    }

    function normalizeUrl(path) {
        path = (path || '').trim();
        if (!path) return '';
        if (/^https?:\/\//i.test(path)) return path;
        if (path.startsWith('/')) return path;
        return '/' + path;
    }

    function setPreview(path) {
        if (!el.preview) return;

        const src = normalizeUrl(path);
        if (!src) {
            el.preview.innerHTML = PLACEHOLDER;
            return;
        }

        const safeSrc = encodeURI(src);
        el.preview.innerHTML = `
            <div class="a2-album-cover-preview-wrap">
                <img src="${safeSrc}" alt="cover" class="a2-album-cover-preview-img">
            </div>
        `;
    }

    function getCard(target) {
        return target.closest('.a2-album-img-card');
    }

    function resetAllCoverBadges() {
        document.querySelectorAll('.a2-album-img-card').forEach(card => {
            const badge = card.querySelector('.a2-cover-badge');
            const btn = card.querySelector('.a2-btn-set-cover');

            if (badge) {
                badge.classList.remove('a2-pill-active');
                badge.classList.add('a2-pill-gray');
                badge.textContent = '—';
            }

            if (btn) btn.disabled = false;
        });
    }

    async function requestJson(url, method = 'POST') {
        const fd = new FormData();
        fd.append('_token', token);
        if (method === 'DELETE') fd.append('_method', 'DELETE');

        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: fd,
            credentials: 'same-origin',
        });

        const ct = res.headers.get('content-type') || '';
        let json = {};

        if (ct.includes('application/json')) {
            json = await res.json().catch(() => ({}));
        } else {
            const txt = await res.text().catch(() => '');
            json = {
                ok: false,
                message: 'الرد ليس JSON (تحقق من routes/controller).',
                _html: txt.slice(0, 300),
            };
        }

        return { ok: res.ok && json.ok !== false, status: res.status, json };
    }

    async function uploadCover() {
        const f = el.fileInput?.files?.[0];
        if (!f) {
            setMsg('اختر ملف صورة أولاً');
            return;
        }

        setMsg('جاري الرفع...');
        if (el.uploadBtn) el.uploadBtn.disabled = true;

        const fd = new FormData();
        fd.append('image', f);
        fd.append('_token', token);

        try {
            const res = await fetch(uploadUrl, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            });

            const json = await res.json().catch(() => ({}));

            if (!res.ok) {
                setMsg(json.message || 'فشل الرفع');
                return;
            }

            const path = json.path || json.url || (json.data && (json.data.path || json.data.url)) || '';
            if (!path) {
                setMsg('تم الرفع لكن لم يتم إرجاع path');
                return;
            }

            if (el.hidden) el.hidden.value = path;
            setPreview(path);
            setMsg('تم رفع صورة الغلاف. (صور الألبوم تُدار من الأسفل).');
        } catch (e) {
            setMsg('خطأ في الاتصال');
        } finally {
            if (el.uploadBtn) el.uploadBtn.disabled = false;
        }
    }

    function clearCover() {
        if (el.hidden) el.hidden.value = '';
        if (el.fileInput) el.fileInput.value = '';
        setPreview('');
        setMsg('');
    }

    async function handleGridClick(e) {
        const openBtn = e.target.closest('.a2-album-img-open');
        if (openBtn) {
            e.preventDefault();

            const card = getCard(openBtn);
            if (!card) return;

            const imgPath = card.getAttribute('data-img-path') || '';
            if (imgPath) {
                if (el.hidden) el.hidden.value = imgPath;
                setPreview(imgPath);
                setMsg('تم عرض الصورة في Preview (اضغط "تعيين كغلاف" لتثبيتها).');
            }
            return;
        }

        const setBtn = e.target.closest('.a2-btn-set-cover');
        if (setBtn) {
            const card = getCard(setBtn);
            if (!card) return;

            const url = card.getAttribute('data-set-cover-url');
            const imgPath = card.getAttribute('data-img-path') || '';
            if (!url) return;

            setBtn.disabled = true;
            setMsg('جاري تعيين الغلاف...');

            const r = await requestJson(url, 'POST');
            if (!r.ok) {
                setMsg(r.json.message || 'فشل تعيين الغلاف');
                setBtn.disabled = false;
                return;
            }

            if (el.hidden) el.hidden.value = r.json.cover || imgPath;
            setPreview(el.hidden?.value || '');
            resetAllCoverBadges();

            const badge = card.querySelector('.a2-cover-badge');
            if (badge) {
                badge.classList.remove('a2-pill-gray');
                badge.classList.add('a2-pill-active');
                badge.textContent = 'Cover';
            }

            setBtn.disabled = true;
            setMsg('تم تعيين الصورة كغلاف.');
            return;
        }

        const delBtn = e.target.closest('.a2-btn-del-img');
        if (delBtn) {
            const card = getCard(delBtn);
            if (!card) return;

            const url = card.getAttribute('data-delete-url');
            if (!url) return;

            if (!confirm('حذف الصورة؟')) return;

            delBtn.disabled = true;
            setMsg('جاري حذف الصورة...');

            const r = await requestJson(url, 'DELETE');
            if (!r.ok) {
                setMsg(r.json.message || 'فشل حذف الصورة');
                delBtn.disabled = false;
                return;
            }

            card.remove();
            setMsg('تم حذف الصورة.');
            return;
        }
    }

    el.uploadBtn?.addEventListener('click', uploadCover);
    el.clearBtn?.addEventListener('click', clearCover);
    document.addEventListener('click', handleGridClick);
})();
</script>
@endsection