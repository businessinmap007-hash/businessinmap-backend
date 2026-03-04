@extends('admin-v2.layouts.master')

@section('title','إضافة ألبوم')
@section('body_class','admin-v2-albums-create')

@section('content')
@php
  $imageNow = old('image', '');
@endphp

<div class="a2-page">
  <div class="a2-card" style="max-width:980px;margin:0 auto;">
    <div class="a2-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
      <div>
        <h2 class="a2-title">إضافة ألبوم</h2>
        <div class="a2-hint">أضف الغلاف والعناوين والأوصاف</div>
      </div>
      <div class="a2-actionsbar">
        <a class="a2-btn a2-btn-ghost" href="{{ route('admin.albums.index') }}">رجوع</a>
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

    <form method="POST" action="{{ route('admin.albums.store') }}" class="a2-form">
      @csrf

      <div style="display:grid;grid-template-columns:340px 1fr;gap:12px;">
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

        <div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div>
              <label class="a2-label">User (اختياري)</label>
              <select class="a2-select" name="user_id">
                <option value="">—</option>
                @foreach(($users ?? []) as $u)
                  <option value="{{ $u->id }}" @selected((string)$u->id === (string)old('user_id'))>
                    #{{ $u->id }} - {{ $u->name ?? '—' }} @if($u->email) ({{ $u->email }}) @endif
                  </option>
                @endforeach
              </select>
            </div>

            <div>
              <label class="a2-label">Title AR</label>
              <input class="a2-input" name="title_ar" value="{{ old('title_ar') }}" maxlength="191">
            </div>

            <div>
              <label class="a2-label">Title EN</label>
              <input class="a2-input" name="title_en" value="{{ old('title_en') }}" maxlength="191" dir="ltr">
            </div>

            <div style="grid-column:1/-1;">
              <label class="a2-label">Description AR</label>
              <textarea class="a2-textarea" name="description_ar" rows="4">{{ old('description_ar') }}</textarea>
            </div>

            <div style="grid-column:1/-1;">
              <label class="a2-label">Description EN</label>
              <textarea class="a2-textarea" name="description_en" rows="4" dir="ltr">{{ old('description_en') }}</textarea>
            </div>
          </div>
        </div>
      </div>

      <div class="a2-actionsbar" style="margin-top:14px;">
        <button type="submit" class="a2-btn a2-btn-primary">حفظ</button>
        <a class="a2-btn a2-btn-ghost" href="{{ route('admin.albums.index') }}">إلغاء</a>
      </div>

    </form>
  </div>
</div>

<script>
(function(){
  const uploadUrl = @json(route('admin.upload.image'));
  const token = @json(csrf_token());

  const fileInput = document.getElementById('a2AlbumFile');
  const uploadBtn = document.getElementById('a2AlbumUploadBtn');
  const clearBtn  = document.getElementById('a2AlbumClearBtn');
  const msgEl     = document.getElementById('a2AlbumUploadMsg');
  const hiddenEl  = document.getElementById('a2AlbumImage');
  const previewEl = document.getElementById('a2AlbumPreview');

  function setMsg(t){ msgEl.textContent = t || ''; }

  function setPreview(path){
    if(!path){
      previewEl.innerHTML = '<div style="width:100%;height:260px;border-radius:18px;border:1px dashed var(--a2-border-2);display:flex;align-items:center;justify-content:center;" class="a2-muted">اختر صورة</div>';
      return;
    }
    // عرض بسيط للصورة
    previewEl.innerHTML = '<img src="'+path+'" style="width:100%;max-width:300px;height:300px;object-fit:cover;border-radius:18px;border:1px solid var(--a2-border);" />';
  }

  async function upload(){
    const f = fileInput.files && fileInput.files[0];
    if(!f){ setMsg('اختر ملف صورة أولاً'); return; }

    setMsg('جاري الرفع...');
    uploadBtn.disabled = true;

    const fd = new FormData();
    fd.append('image', f);
    fd.append('_token', token);

    try{
      const res = await fetch(uploadUrl, { method:'POST', body: fd });
      const json = await res.json().catch(()=> ({}));

      if(!res.ok){
        setMsg(json.message || 'فشل الرفع');
        uploadBtn.disabled = false;
        return;
      }

      const path = json.path || json.url || (json.data && (json.data.path || json.data.url)) || '';
      if(!path){
        setMsg('تم الرفع لكن لم يتم إرجاع path');
        uploadBtn.disabled = false;
        return;
      }

      hiddenEl.value = path;
      setPreview(path);
      setMsg('تم رفع الصورة');
    }catch(e){
      setMsg('خطأ في الاتصال');
    }finally{
      uploadBtn.disabled = false;
    }
  }

  function clearImg(){
    hiddenEl.value = '';
    fileInput.value = '';
    setPreview('');
    setMsg('');
  }

  uploadBtn.addEventListener('click', upload);
  clearBtn.addEventListener('click', clearImg);
})();
</script>
@endsection