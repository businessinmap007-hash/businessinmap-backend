@extends('admin-v2.layouts.master')

@section('title','عرض ألبوم')
@section('body_class','admin-v2-albums-show')

@section('content')
@php
  $a = $album;

  $titleAr = (string)($a->title_ar ?? '');
  $titleEn = (string)($a->title_en ?? '');
  $title   = $titleAr !== '' ? $titleAr : ($titleEn !== '' ? $titleEn : ('#'.$a->id));

  $imgPath = (string)($a->image ?? '');

  $imgs = $a->images ?? collect();

  $userShowUrl = null;
  if ($a->user_id) {
    try { $userShowUrl = route('admin.users.show', $a->user_id); } catch (\Throwable $e) {}
  }

  $fixUrl = function($path){
    $path = trim((string)$path);
    if ($path === '') return '';

    // absolute
    if (preg_match('~^https?://~i', $path)) return $path;

    // already root
    if (str_starts_with($path, '/')) return $path;

    // otherwise make it root-based
    return '/'.$path;
  };

  $userName  = (string)($a->user->name ?? '');
  $userLabel = $userName !== '' ? $userName : ($a->user_id ? '#'.$a->user_id : '—');
@endphp


<div class="a2-page">
  <div class="a2-card">

    <div class="a2-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
      <div>
        <h2 class="a2-title">ألبوم #{{ $a->id }}</h2>
        <div class="a2-muted a2-clip a2-clip-10" title="{{ $title }}">{{ $title }}</div>
      </div>

      <div class="a2-actionsbar">
        <a class="a2-btn a2-btn-ghost" href="{{ route('admin.albums.index') }}">رجوع</a>
        <a class="a2-btn a2-btn-primary" href="{{ route('admin.albums.edit', $a->id) }}">تعديل</a>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:340px 1fr;gap:12px;">

      {{-- LEFT: Cover + Album Images --}}
      <div>

        {{-- Cover card --}}
        <div class="a2-card" style="box-shadow:none;border:1px solid var(--a2-border);">
          <div class="a2-header">
            <h3 class="a2-title" style="font-size:16px;">الغلاف</h3>
          </div>

          <div class="a2-body" style="padding:14px;">
            @if($imgPath !== '')
              <x-admin-v2.image :path="$imgPath" size="300" radius="18px" />
              <div class="a2-muted a2-clip a2-clip-10" dir="ltr" style="margin-top:10px;" title="{{ $imgPath }}">
                {{ $imgPath }}
              </div>
            @else
              <div style="width:100%;height:260px;border-radius:18px;border:1px dashed var(--a2-border-2);display:flex;align-items:center;justify-content:center;" class="a2-muted">
                لا يوجد غلاف
              </div>
            @endif
          </div>
        </div>

        {{-- Album images card --}}
        <div class="a2-card" style="box-shadow:none;border:1px solid var(--a2-border);margin-top:12px;">
          <div class="a2-header">
            <h3 class="a2-title" style="font-size:16px;">صور الألبوم</h3>
            <div class="a2-hint">{{ $imgs->count() }} صورة</div>
          </div>

          <div class="a2-body" style="padding:14px;">
            @if($imgs->count())
              <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:10px;">
                @foreach($imgs as $img)
                  @php
                    $pRaw = (string)($img->path ?? $img->url ?? $img->image ?? '');$p = $fixUrl($pRaw);
                  @endphp

                  <div class="a2-card" style="box-shadow:none;border:1px solid var(--a2-border);padding:8px;">
                    @if($p !== '')
                      <a href="{{ $p }}" target="_blank" rel="noopener" style="display:block;">
                        <x-admin-v2.image :path="$p" size="96" radius="12px" />
                      </a>
                      <div class="a2-muted a2-clip a2-clip-10" dir="ltr" title="{{ $p }}" style="margin-top:6px;">
                        {{ $p }}
                      </div>
                    @else
                      <div style="width:96px;height:96px;border-radius:12px;border:1px dashed var(--a2-border-2);display:flex;align-items:center;justify-content:center;" class="a2-muted">
                        —
                      </div>
                    @endif
                  </div>
                @endforeach
              </div>
            @else
              <div class="a2-muted">لا توجد صور إضافية داخل الألبوم.</div>
            @endif
          </div>
        </div>

      </div>

      {{-- RIGHT: Details --}}
      <div class="a2-card" style="box-shadow:none;border:1px solid var(--a2-border);">
        <div class="a2-header">
          <h3 class="a2-title" style="font-size:16px;">البيانات</h3>
        </div>

        <div class="a2-body" style="padding:14px;">
          <div class="a2-row">
            <div class="a2-muted">Title AR</div>
            <div class="a2-clip a2-clip-10" title="{{ $titleAr }}">{{ $titleAr !== '' ? $titleAr : '—' }}</div>
          </div>

          <div class="a2-row">
            <div class="a2-muted">Title EN</div>
            <div class="a2-clip a2-clip-10" dir="ltr" title="{{ $titleEn }}">{{ $titleEn !== '' ? $titleEn : '—' }}</div>
          </div>

          <div class="a2-row">
            <div class="a2-muted">Description AR</div>
            <div style="white-space:pre-wrap;">{{ (string)($a->description_ar ?? '—') }}</div>
          </div>

          <div class="a2-row">
            <div class="a2-muted">Description EN</div>
            <div style="white-space:pre-wrap;" dir="ltr">{{ (string)($a->description_en ?? '—') }}</div>
          </div>

          <div class="a2-row">


          
            <div class="a2-muted">User</div>
            <div>
              @if($a->user_id && $userShowUrl)
                <a class="a2-link a2-clip a2-clip-10" href="{{ $userShowUrl }}" title="{{ $userLabel }}">{{ $userLabel }}</a>
                <div class="a2-muted a2-clip a2-clip-10" dir="ltr" title="#{{ (int)$a->user_id }}">
                  
                </div>
              
              @else
                —
              @endif
            </div>
          </div>
          <div class="a2-row">
            <div class="a2-muted">Uesr</div><div dir="ltr">{{ (int)$a->user_id  }}</div>
                  
          </div>
          <div class="a2-row">
            <div class="a2-muted">Email</div>
            <div dir="ltr">{{ ($a->user?->email)}}</div>
          </div>

          <div class="a2-row">
            <div class="a2-muted">Created</div>
            <div dir="ltr">{{ $a->created_at ? $a->created_at->format('Y-m-d H:i') : '—' }}</div>
          </div>

          <div class="a2-row">
            <div class="a2-muted">Updated</div>
            <div dir="ltr">{{ $a->updated_at ? $a->updated_at->format('Y-m-d H:i') : '—' }}</div>
          </div>
        </div>
      </div>

    </div>

  </div>
</div>
@endsection