@props([
    'name',
    'label' => 'Image',
    'path' => null,
    'size' => 120,
    'radius' => '16px',
    'uploadRoute' => 'admin.upload.image',
])

@php
    $uid = 'up_' . md5($name . '_' . $label);
    $uploadUrl = route($uploadRoute);
@endphp

<div class="a2-card" style="padding:14px;">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:10px;">
        <div>
            <div class="a2-card-title" style="font-size:14px;">{{ $label }}</div>
            <div class="a2-card-sub" style="font-size:12px;">files/uploads/...</div>
        </div>

        <button type="button" class="a2-btn a2-btn-ghost"
                onclick="document.getElementById('{{ $uid }}_file').click()">
            رفع
        </button>
    </div>

    <div style="display:flex;flex-direction:column;gap:10px;align-items:center;text-align:center;">
        <x-admin-v2.image :path="$path" :size="$size" :radius="$radius" />

        {{-- hidden input that will be submitted with the form --}}
        <input type="hidden" name="{{ $name }}" id="{{ $uid }}_input" value="{{ old($name, $path) }}">

        <input id="{{ $uid }}_file" type="file" accept="image/*" style="display:none"
               onchange="AdminV2ImgUploader.upload('{{ $uid }}', this.files[0], '{{ $uploadUrl }}')">

        <div class="a2-muted" id="{{ $uid }}_text" style="font-size:12px;word-break:break-all;">
            {{ old($name, $path) ?: 'No image' }}
        </div>

        <div class="a2-help" id="{{ $uid }}_help" style="display:none;"></div>
    </div>
</div>

@once
<script>
window.AdminV2ImgUploader = window.AdminV2ImgUploader || {
  async upload(prefix, file, url){
    if(!file) return;

    const input = document.getElementById(prefix + '_input');
    const text  = document.getElementById(prefix + '_text');
    const help  = document.getElementById(prefix + '_help');

    help.style.display = 'block';
    help.className = 'a2-help';
    help.textContent = 'جارٍ الرفع...';

    const fd = new FormData();
    fd.append('file', file);

    try{
      const res = await fetch(url, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': @json(csrf_token()),
          'Accept': 'application/json'
        },
        body: fd
      });

      const data = await res.json();

      if(!res.ok || !data || !data.path){
        throw new Error(data?.message || 'Upload failed');
      }

      input.value = data.path;   // files/uploads/xxx.jpg
      text.textContent = data.path;

      help.className = 'a2-help a2-help-success';
      help.textContent = 'تم الرفع ✅ اضغط حفظ لتثبيت المسار';
    }catch(e){
      help.className = 'a2-help a2-help-danger';
      help.textContent = 'فشل الرفع: ' + (e?.message || e);
    }
  }
};
</script>
@endonce
