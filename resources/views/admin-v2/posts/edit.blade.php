@extends('admin-v2.layouts.master')

@section('title','Edit Post')
@section('body_class','admin-v2-posts')

@section('content')
@php
  $qsKeep  = request()->only(['q','type','active','per_page','sort','dir']);
  $imgPath = old('image_current', $post->image ?? null);

  $gallery = $post->images ?? collect();

  // build thumbs: main (posts.image) + gallery (images table) without duplicates
  $thumbs = collect();

  if(!empty($post->image)){
    $thumbs->push((object)[
      'id' => null,
      'image' => $post->image,
      'is_main' => true,
    ]);
  }

  foreach($gallery as $g){
    if(empty($g->image)) continue;
    if(!empty($post->image) && $g->image === $post->image) continue;

    $thumbs->push((object)[
      'id' => $g->id,
      'image' => $g->image,
      'is_main' => false,
    ]);
  }

  $mainPreview = $thumbs->first()->image ?? null;
@endphp

<div class="a2-page">
  <div class="a2-card">

    <div class="a2-header">
      <h2 class="a2-title">تعديل المنشور</h2>

      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a class="a2-btn a2-btn-ghost" href="{{ route('admin.posts.show', ['post'=>$post->id] + $qsKeep) }}">عرض</a>
        <a class="a2-btn a2-btn-ghost" href="{{ route('admin.posts.index', $qsKeep) }}">رجوع</a>
      </div>
    </div>

    @if(session('success'))
      <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
      <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
    @endif

    <form method="POST"
          action="{{ route('admin.posts.update', ['post'=>$post->id] + $qsKeep) }}"
          enctype="multipart/form-data">
      @csrf
      @method('PUT')

      <input type="hidden" name="image_current" value="{{ $imgPath }}">

      <div style="display:grid;grid-template-columns: 320px 1fr;gap:16px;align-items:start;">

        {{-- LEFT: Images + Status --}}
        <div class="a2-card" style="padding:14px;">

          {{-- Main preview --}}
          <div style="display:flex;justify-content:center;margin-bottom:10px;">
            <div id="imgPreviewBox">
              <x-admin-v2.image :path="$mainPreview" size="280" radius="18px" />
            </div>
          </div>

          {{-- Thumbnails + delete --}}
          @if($thumbs->count())
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:12px;">
              @foreach($thumbs as $t)
                @php($path = $t->image ?? null)
                @continue(empty($path))

                <div style="position:relative;border-radius:14px;overflow:hidden;border:1px solid var(--a2-border);">
                  <a href="{{ asset(ltrim($path,'/')) }}" target="_blank" style="display:block">
                    <x-admin-v2.image :path="$path" size="90" radius="14px" />
                  </a>

                  {{-- Delete button --}}
                  <form method="POST"
                        action="{{ $t->is_main
                          ? route('admin.posts.main_image.destroy', ['post'=>$post->id] + $qsKeep)
                          : route('admin.posts.images.destroy', ['post'=>$post->id, 'image'=>$t->id] + $qsKeep)
                        }}"
                        style="position:absolute;top:6px;left:6px;"
                        onsubmit="return confirm('تأكيد حذف الصورة؟');">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="a2-btn a2-btn-danger"
                            style="height:26px;padding:0 8px;font-size:12px;border-radius:10px;">
                      حذف
                    </button>
                  </form>
                </div>
              @endforeach
            </div>
          @endif

          {{-- Upload new main image --}}
          <div style="display:grid;gap:10px;">
            <label class="a2-hint" style="font-weight:900;">صورة المنشور (رئيسية تكتب في posts.image)</label>

            <input type="file"
                   name="image"
                   id="postImageInput"
                   class="a2-input"
                   accept="image/*"
                   style="padding-top:8px;">

            <div class="a2-hint">
              يُسمح: jpg/png/jpeg — الحفظ داخل <b>files/uploads</b>
            </div>
          </div>

          <hr style="border:0;border-top:1px solid var(--a2-border);margin:12px 0;">

          <div style="display:grid;gap:10px;">
            <label class="a2-hint" style="font-weight:900;">الحالة</label>
            <select class="a2-select" name="is_active">
              <option value="1" @selected((string)old('is_active', (int)($post->is_active ?? 0)) === '1')>Active</option>
              <option value="0" @selected((string)old('is_active', (int)($post->is_active ?? 0)) === '0')>Inactive</option>
            </select>

            <label class="a2-hint" style="font-weight:900;">تاريخ الانتهاء</label>
            <input class="a2-input"
                   type="date"
                   name="expire_at"
                   value="{{ old('expire_at', $post->expire_at ? \Carbon\Carbon::parse($post->expire_at)->format('Y-m-d') : '') }}"
                   dir="ltr">

            
          </div>

        </div>

        {{-- RIGHT: Fields --}}
        <div class="a2-card" style="padding:14px;">
          <div style="display:grid;gap:12px;">

            <div>
              <label class="a2-hint" style="font-weight:900;">العنوان (AR)</label>
              <input class="a2-input" name="title_ar" value="{{ old('title_ar', $post->title_ar) }}" style="width:100%;">
            </div>

            <div>
              <label class="a2-hint" style="font-weight:900;">العنوان (EN)</label>
              <input class="a2-input" name="title_en" value="{{ old('title_en', $post->title_en) }}" style="width:100%;" dir="ltr">
            </div>

            <div>
              <label class="a2-hint" style="font-weight:900;">المحتوى</label>
              <textarea class="a2-input"
                        name="body"
                        rows="12"
                        style="width:100%;height:auto;padding:10px;line-height:1.9;">{{ old('body', $post->body) }}</textarea>
            </div>

            <div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-start;margin-top:6px;">
              <button type="submit" class="a2-btn a2-btn-primary">حفظ</button>
              <a class="a2-btn a2-btn-ghost" href="{{ route('admin.posts.show', ['post'=>$post->id] + $qsKeep) }}">إلغاء</a>
            </div>

          </div>
        </div>

      </div>
    </form>

  </div>
</div>

<script>
(function(){
  const input = document.getElementById('postImageInput');
  const box = document.getElementById('imgPreviewBox');
  if(!input || !box) return;

  input.addEventListener('change', function(){
    const f = this.files && this.files[0];
    if(!f) return;

    const url = URL.createObjectURL(f);
    box.innerHTML = `
      <div style="
        width:280px;height:280px;border-radius:18px;overflow:hidden;
        background:#f3f4f6;border:1px solid var(--a2-border);
        display:flex;align-items:center;justify-content:center;">
        <img src="${url}" alt="preview" style="width:100%;height:100%;object-fit:cover;display:block;">
      </div>
    `;
  });
})();
</script>
@endsection
