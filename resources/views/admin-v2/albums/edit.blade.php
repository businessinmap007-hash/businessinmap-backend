@extends('admin-v2.layouts.master')

@section('title','تعديل ألبوم')
@section('body_class','admin-v2-albums-edit')

@section('content')
@php
  $a = $album;
  $imageNow = old('image', (string)($a->image ?? ''));
  $imgs = $a->images ?? collect();
@endphp

<div class="a2-page">
  <div class="a2-card" style="max-width:980px;margin:0 auto;">
    <div class="a2-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
      <div>
        <h2 class="a2-title">تعديل ألبوم #{{ $a->id }}</h2>
        <div class="a2-muted" dir="ltr">Created: {{ $a->created_at ? $a->created_at->format('Y-m-d H:i') : '—' }}</div>
      </div>
      <div class="a2-actionsbar">
        <a class="a2-btn a2-btn-ghost" href="{{ route('admin.albums.show', $a->id) }}">رجوع</a>
      </div>
    </div>

    @if ($errors->any())
      <div class="a2-alert a2-alert-danger" style="margin:12px 0;">
        <div class="a2-fw-900" style="margin-bottom:6px;">يوجد أخطاء</div>
        <ul style="margin:0;padding-inline-start:18px;">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <form method="POST" action="{{ route('admin.albums.update', $a->id) }}" class="a2-form">
      @csrf
      @method('PUT')

      {{-- ✅ صف الغلاف + البيانات --}}
      <div style="display:grid;grid-template-columns:340px 1fr;gap:12px;align-items:start;">

        {{-- LEFT (Cover) --}}
        <div>
          <div class="a2-card" style="box-shadow:none;border:1px solid var(--a2-border);">
            <div class="a2-header">
              <h3 class="a2-title" style="font-size:16px;">الغلاف</h3>
            </div>

            <div class="a2-body" style="padding:14px;">
              <div id="a2AlbumPreview">
                @if($imageNow)
                  <x-admin-v2.image :path="$imageNow" size="300" radius="18px" />
                @else
                  <div style="width:100%;height:260px;border-radius:18px;border:1px dashed var(--a2-border-2);display:flex;align-items:center;justify-content:center;" class="a2-muted">
                    اختر صورة
                  </div>
                @endif
              </div>

              <input type="hidden" name="image" id="a2AlbumImage" value="{{ $imageNow }}">

              <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;">
                <input type="file" id="a2AlbumFile" accept="image/*" class="a2-input" style="max-width:100%;">
                <button type="button" class="a2-btn a2-btn-ghost" id="a2AlbumUploadBtn">رفع</button>
                <button type="button" class="a2-btn a2-btn-ghost" id="a2AlbumClearBtn">مسح</button>
              </div>

              <div class="a2-muted" id="a2AlbumUploadMsg" style="margin-top:8px;"></div>
            </div>
          </div>
        </div>

        {{-- RIGHT (Fields) --}}
        <div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div>
              <label class="a2-label">Title AR</label>
              <input class="a2-input" name="title_ar" value="{{ old('title_ar', (string)($a->title_ar ?? '')) }}" maxlength="191">
            </div>

            <div>
              <label class="a2-label">Title EN</label>
              <input class="a2-input" name="title_en" value="{{ old('title_en', (string)($a->title_en ?? '')) }}" maxlength="191" dir="ltr">
            </div>

            <div style="grid-column:1/-1;">
              <label class="a2-label">Description AR</label>
              <textarea class="a2-textarea" name="description_ar" rows="4">{{ old('description_ar', (string)($a->description_ar ?? '')) }}</textarea>
            </div>

            <div style="grid-column:1/-1;">
              <label class="a2-label">Description EN</label>
              <textarea class="a2-textarea" name="description_en" rows="4" dir="ltr">{{ old('description_en', (string)($a->description_en ?? '')) }}</textarea>
            </div>
          </div>
        </div>

      </div>

      {{-- ✅ صور الألبوم تمتد على عرض الصفحة بالكامل --}}
      <div class="a2-card" style="box-shadow:none;border:1px solid var(--a2-border);margin-top:12px;">
        <div class="a2-header" style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
          <h3 class="a2-title" style="font-size:16px;">صور الألبوم</h3>
          <div class="a2-hint">{{ $imgs->count() }} صورة</div>
        </div>

        <div class="a2-body" style="padding:14px;">
          @if($imgs->count())
            <div id="a2AlbumImagesGrid"
                 style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;">
              @foreach($imgs as $img)
                @php
                  $p = (string)($img->path ?? $img->url ?? $img->image ?? '');
                  $isCover = ($p !== '' && (string)$a->image === $p);

                  $setCoverUrl = route('admin.albums.images.set-cover', [$a->id, $img->id]);
                  $deleteUrl   = route('admin.albums.images.delete', [$a->id, $img->id]);
                @endphp

                <div class="a2-card a2-album-img-card"
                     data-img-id="{{ $img->id }}"
                     data-img-path="{{ $p }}"
                     data-set-cover-url="{{ $setCoverUrl }}"
                     data-delete-url="{{ $deleteUrl }}"
                     style="box-shadow:none;border:1px solid var(--a2-border);padding:10px;">

                  @if($p !== '')
                    <button type="button" class="a2-album-img-open" style="all:unset;cursor:pointer;display:block;width:100%;">
                      <x-admin-v2.image :path="$p" size="170" radius="12px" />
                    </button>
                    <div class="a2-muted a2-clip a2-clip-10" dir="ltr" title="{{ $p }}" style="margin-top:6px;">
                      {{ $p }}
                    </div>
                  @else
                    <div style="width:100%;height:170px;border-radius:12px;border:1px dashed var(--a2-border-2);display:flex;align-items:center;justify-content:center;" class="a2-muted">
                      —
                    </div>
                  @endif

                  <div class="a2-album-img-actions" style="display:flex;gap:6px;flex-wrap:wrap;margin-top:10px;align-items:center;">
                    <span class="a2-badge {{ $isCover ? 'a2-badge-success' : 'a2-badge-muted' }} a2-cover-badge">
                      {{ $isCover ? 'Cover' : '—' }}
                    </span>

                    <button type="button" class="a2-btn a2-btn-ghost a2-btn-sm a2-btn-set-cover" @disabled($isCover)>
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

      <div class="a2-actionsbar" style="margin-top:14px;">
        <button type="submit" class="a2-btn a2-btn-primary">حفظ</button>
        <a class="a2-btn a2-btn-ghost" href="{{ route('admin.albums.show', $a->id) }}">إلغاء</a>
      </div>

    </form>
  </div>
</div>


<script>
(() => {
  const uploadUrl = @json(route('admin.upload.image'));
  const token     = @json(csrf_token());

  // ===== Elements =====
  const el = {
    fileInput: document.getElementById('a2AlbumFile'),
    uploadBtn: document.getElementById('a2AlbumUploadBtn'),
    clearBtn:  document.getElementById('a2AlbumClearBtn'),
    msg:       document.getElementById('a2AlbumUploadMsg'),
    hidden:    document.getElementById('a2AlbumImage'),
    preview:   document.getElementById('a2AlbumPreview'),
  };

  // ===== Helpers =====
  const PLACEHOLDER = `
    <div style="width:100%;height:260px;border-radius:18px;border:1px dashed var(--a2-border-2);
                display:flex;align-items:center;justify-content:center;" class="a2-muted">
      اختر صورة
    </div>
  `;

  function setMsg(text) {
      if (!el.msg) return;
      el.msg.textContent = text || '';
    }
    function normalizeUrl(path){
    path = (path || '').trim();
    if(!path) return '';

    // absolute http(s)
    if(/^https?:\/\//i.test(path)) return path;

    // starts with /
    if(path.startsWith('/')) return path;

    // storage paths sometimes: "storage/..." or "uploads/..."
    return '/' + path;
  }

  function setPreview(path){
    if(!el.preview) return;

    const src = normalizeUrl(path);
    if(!src){
      el.preview.innerHTML = PLACEHOLDER;
      return;
    }

    const safeSrc = encodeURI(src);
    el.preview.innerHTML =
      `<img src="${safeSrc}"
            style="width:100%;max-width:300px;height:300px;object-fit:cover;border-radius:18px;border:1px solid var(--a2-border);" />`;
  }

 

  function getCard(target) {
    return target.closest('.a2-album-img-card');
  }

  function resetAllCoverBadges() {
    document.querySelectorAll('.a2-album-img-card').forEach(card => {
      const badge = card.querySelector('.a2-cover-badge');
      const btn   = card.querySelector('.a2-btn-set-cover');
      if (badge) {
        badge.classList.remove('a2-badge-success');
        badge.classList.add('a2-badge-muted');
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
      method: 'POST', // Laravel-friendly (supports _method)
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

  // ===== Cover Upload (hidden input + preview) =====
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
      const res  = await fetch(uploadUrl, { method: 'POST', body: fd, credentials: 'same-origin' });
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

  // ===== Album Images actions (preview / set cover / delete) =====
  async function handleGridClick(e) {
    // Preview from album image
    const openBtn = e.target.closest('.a2-album-img-open');
    if (openBtn) {
      e.preventDefault();

      const card = getCard(openBtn);
      if(!card) return;

      const imgPath = card.getAttribute('data-img-path') || '';
      if(imgPath){
        if (el.hidden) el.hidden.value = imgPath; // اختياري
        setPreview(imgPath);
        setMsg('تم عرض الصورة في Preview (اضغط "تعيين كغلاف" لتثبيتها).');
      }
      return;

    }

    // Set cover
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
        badge.classList.remove('a2-badge-muted');
        badge.classList.add('a2-badge-success');
        badge.textContent = 'Cover';
      }

      setBtn.disabled = true;
      setMsg('تم تعيين الصورة كغلاف.');
      return;
    }

    // Delete image
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

  // ===== Bind =====
  el.uploadBtn?.addEventListener('click', uploadCover);
  el.clearBtn?.addEventListener('click', clearCover);

  // event delegation for grid
  document.addEventListener('click', handleGridClick);

})();
</script>
@endsection