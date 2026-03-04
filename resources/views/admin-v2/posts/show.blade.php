@extends('admin-v2.layouts.master')

@section('title','View Post')
@section('body_class','admin-v2-posts')

@section('content')
@php
    $qsKeep = $qsKeep ?? request()->only(['q','type','active','per_page','sort','dir']);

    $gallery = $post->images ?? collect(); // morphMany
    $main    = $post->image ?: ($gallery->first()->image ?? null);

    // thumbs: main + gallery (بدون تكرار)
    $thumbs = collect();
    if(!empty($post->image)){
        $thumbs->push((object)[
            'id' => null,
            'image' => $post->image,
            'is_main' => true,
        ]);
    }
    foreach($gallery as $g){
        if(!empty($g->image) && $g->image !== $post->image){
            $thumbs->push((object)[
                'id' => $g->id,
                'image' => $g->image,
                'is_main' => false,
            ]);
        }
    }

    $isActive = (int)($post->is_active ?? 0) === 1;
    $user     = $post->user ?? null;
@endphp

<div class="a2-page">
  <div class="a2-card">

    <div class="a2-header">
      <h2 class="a2-title">عرض المنشور</h2>

      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a class="a2-btn a2-btn-primary" href="{{ route('admin.posts.edit', ['post'=>$post->id] + $qsKeep) }}">تعديل</a>
        <a class="a2-btn a2-btn-ghost" href="{{ route('admin.posts.index', $qsKeep) }}">رجوع</a>
      </div>
    </div>

    @if(session('success'))
      <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
      <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
    @endif

    <div style="display:grid;grid-template-columns: 320px 1fr;gap:16px;align-items:start;">

      {{-- Left: Images + Meta (نفس edit) --}}
      <div class="a2-card" style="padding:14px;">

        {{-- Main preview --}}
        <div style="display:flex;justify-content:center;margin-bottom:10px;">
          <div id="imgPreviewBox">
            <x-admin-v2.image :path="$main" size="280" radius="18px" />
          </div>
        </div>

        {{-- Thumbnails --}}
        @if($thumbs->count())
          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:12px;">
            @foreach($thumbs as $t)
              @php($path = $t->image ?? null)
              @continue(empty($path))

              <a href="{{ asset(ltrim($path,'/')) }}"
                 target="_blank"
                 title="فتح الصورة"
                 style="display:block;border-radius:14px;overflow:hidden;border:1px solid var(--a2-border);">
                <x-admin-v2.image :path="$path" size="90" radius="14px" />
              </a>
            @endforeach
          </div>
        @endif

        <hr style="border:0;border-top:1px solid var(--a2-border);margin:12px 0;">

        {{-- Meta (بدل inputs في edit) --}}
        <div style="display:grid;gap:10px;">

          <div style="display:flex;justify-content:space-between;gap:10px;align-items:center;">
            <div class="a2-hint" style="font-weight:900;">الحالة</div>
            <span class="a2-pill {{ $isActive ? 'a2-pill-active' : 'a2-pill-inactive' }}">
              {{ $isActive ? 'Active' : 'Inactive' }}
            </span>
          </div>

          <div style="display:flex;justify-content:space-between;gap:10px;align-items:center;">
            <div class="a2-hint" style="font-weight:900;">تاريخ الانتهاء</div>
            <div class="a2-fw-900" dir="ltr">
              {{ $post->expire_at ? \Carbon\Carbon::parse($post->expire_at)->format('Y-m-d') : '—' }}
            </div>
          </div>
          <div style="display:flex;justify-content:space-between;gap:10px;align-items:center;">
            <div class="a2-hint" style="font-weight:900;">ID</div>
            <div class="a2-fw-900">{{ $post->id }}</div>
          </div>

          <div style="display:flex;justify-content:space-between;gap:10px;align-items:center;">
            <div class="a2-hint" style="font-weight:900;">Shares</div>
            <div class="a2-fw-900">{{ (int)($post->share_count ?? 0) }}</div>
          </div>

          <hr style="border:0;border-top:1px solid var(--a2-border);margin:10px 0;">

          <div style="display:flex;justify-content:space-between;gap:10px;align-items:center;">
            <div class="a2-hint" style="font-weight:900;">الحساب</div>
            <div class="a2-fw-900">{{ $user?->name ?: '—' }}</div>
          </div>

          <div style="display:flex;justify-content:space-between;gap:10px;align-items:center;">
            <div class="a2-hint" style="font-weight:900;">Email</div>
            <div class="a2-fw-900" dir="ltr">{{ $user?->email ?: '—' }}</div>
          </div>

          <div style="display:flex;justify-content:space-between;gap:10px;align-items:center;">
            <div class="a2-hint" style="font-weight:900;">Phone</div>
            <div class="a2-fw-900" dir="ltr">{{ $user?->phone ?: '—' }}</div>
          </div>

        </div>
      </div>

      {{-- Right: Fields (نفس edit لكن readonly) --}}
      <div class="a2-card" style="padding:14px;">
        <div style="display:grid;gap:12px;">

          <div>
            <label class="a2-hint" style="font-weight:900;">العنوان (AR)</label>
            <div class="a2-input" style="width:100%;display:flex;align-items:center;">
              {{ $post->title_ar ?: '—' }}
            </div>
          </div>

          <div>
            <label class="a2-hint" style="font-weight:900;">العنوان (EN)</label>
            <div class="a2-input" style="width:100%;display:flex;align-items:center;" dir="ltr">
              {{ $post->title_en ?: '—' }}
            </div>
          </div>

          <div>
            <label class="a2-hint" style="font-weight:900;">المحتوى</label>
            <div class="a2-input" style="width:100%;height:auto;padding:10px;line-height:1.9;white-space:pre-wrap;">
              {{ $post->body ?: '—' }}
            </div>
          </div>

        </div>
      </div>

    </div>

  </div>
</div>
@endsection
