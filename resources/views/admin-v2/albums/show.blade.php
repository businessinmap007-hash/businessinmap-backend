@extends('admin-v2.layouts.master')

@section('title', 'عرض ألبوم')
@section('body_class', 'admin-v2-albums-show')

@section('content')
@php
    $a = $album;

    $titleAr = (string) ($a->title_ar ?? '');
    $titleEn = (string) ($a->title_en ?? '');
    $title = $titleAr !== '' ? $titleAr : ($titleEn !== '' ? $titleEn : ('#' . $a->id));

    $imgPath = (string) ($a->image ?? '');
    $imgs = $a->images ?? collect();

    $userShowUrl = null;
    if ($a->user_id) {
        try {
            $userShowUrl = route('admin.users.show', $a->user_id);
        } catch (\Throwable $e) {
            $userShowUrl = null;
        }
    }

    $fixUrl = function ($path) {
        $path = trim((string) $path);
        if ($path === '') return '';
        if (preg_match('~^https?://~i', $path)) return $path;
        if (str_starts_with($path, '/')) return $path;
        return '/' . $path;
    };

    $userName = (string) ($a->user->name ?? '');
    $userLabel = $userName !== '' ? $userName : ($a->user_id ? '#' . $a->user_id : '—');
    $userEmail = (string) ($a->user?->email ?? '');
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">ألبوم #{{ $a->id }}</h1>
            <div class="a2-page-subtitle a2-clip" title="{{ $title }}">
                {{ $title }}
            </div>
        </div>

        <div class="a2-page-actions">
            <a class="a2-btn a2-btn-ghost" href="{{ route('admin.albums.index') }}">رجوع</a>
            <a class="a2-btn a2-btn-primary" href="{{ route('admin.albums.edit', $a->id) }}">تعديل</a>
        </div>
    </div>

    <div class="a2-card">
        <div class="a2-album-show-grid">

            {{-- LEFT: Cover + Images --}}
            <div>
                <div class="a2-card a2-card-flat">
                    <div class="a2-header">
                        <h3 class="a2-section-title">الغلاف</h3>
                    </div>

                    <div class="a2-album-cover-body">
                        @if($imgPath !== '')
                            <div class="a2-album-cover-preview-wrap">
                                <img src="{{ asset($imgPath) }}" alt="cover" class="a2-album-cover-preview-img">
                            </div>

                            <div class="a2-muted a2-clip a2-mt-8" dir="ltr" title="{{ $imgPath }}">
                                {{ $imgPath }}
                            </div>
                        @else
                            <div class="a2-album-cover-placeholder a2-muted">
                                لا يوجد غلاف
                            </div>
                        @endif
                    </div>
                </div>

                <div class="a2-card a2-card-flat a2-mt-12">
                    <div class="a2-header">
                        <h3 class="a2-section-title">صور الألبوم</h3>
                        <div class="a2-hint">{{ $imgs->count() }} صورة</div>
                    </div>

                    <div class="a2-album-gallery-body">
                        @if($imgs->count())
                            <div class="a2-album-show-images-grid">
                                @foreach($imgs as $img)
                                    @php
                                        $pRaw = (string) ($img->path ?? $img->url ?? $img->image ?? '');
                                        $p = $fixUrl($pRaw);
                                    @endphp

                                    <div class="a2-card a2-card-flat a2-album-show-image-card">
                                        @if($p !== '')
                                            <a href="{{ $p }}" target="_blank" rel="noopener" class="a2-album-show-image-link">
                                                <img src="{{ $p }}" alt="album-image" class="a2-album-show-thumb">
                                            </a>

                                            <div class="a2-muted a2-clip a2-mt-8" dir="ltr" title="{{ $p }}">
                                                {{ $p }}
                                            </div>
                                        @else
                                            <div class="a2-album-show-thumb-placeholder a2-muted">
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
            <div class="a2-card a2-card-flat">
                <div class="a2-header">
                    <h3 class="a2-section-title">البيانات</h3>
                </div>

                <div class="a2-album-details-body">
                    <div class="a2-kv">
                        <div class="a2-kv-row">
                            <div class="a2-kv-key">Title AR</div>
                            <div class="a2-kv-val a2-clip" title="{{ $titleAr }}">
                                {{ $titleAr !== '' ? $titleAr : '—' }}
                            </div>
                        </div>

                        <div class="a2-kv-row">
                            <div class="a2-kv-key">Title EN</div>
                            <div class="a2-kv-val a2-clip" dir="ltr" title="{{ $titleEn }}">
                                {{ $titleEn !== '' ? $titleEn : '—' }}
                            </div>
                        </div>

                        <div class="a2-kv-row">
                            <div class="a2-kv-key">Description AR</div>
                            <div class="a2-kv-val">
                                <div class="a2-view-box a2-album-desc-box">
                                    {{ (string) ($a->description_ar ?? '—') }}
                                </div>
                            </div>
                        </div>

                        <div class="a2-kv-row">
                            <div class="a2-kv-key">Description EN</div>
                            <div class="a2-kv-val">
                                <div class="a2-view-box a2-album-desc-box" dir="ltr">
                                    {{ (string) ($a->description_en ?? '—') }}
                                </div>
                            </div>
                        </div>

                        <div class="a2-kv-row">
                            <div class="a2-kv-key">User</div>
                            <div class="a2-kv-val">
                                @if($a->user_id && $userShowUrl)
                                    <a class="a2-link a2-clip" href="{{ $userShowUrl }}" title="{{ $userLabel }}">
                                        {{ $userLabel }}
                                    </a>
                                @else
                                    —
                                @endif
                            </div>
                        </div>

                        <div class="a2-kv-row">
                            <div class="a2-kv-key">User ID</div>
                            <div class="a2-kv-val" dir="ltr">
                                {{ $a->user_id ? (int) $a->user_id : '—' }}
                            </div>
                        </div>

                        <div class="a2-kv-row">
                            <div class="a2-kv-key">Email</div>
                            <div class="a2-kv-val" dir="ltr">
                                {{ $userEmail !== '' ? $userEmail : '—' }}
                            </div>
                        </div>

                        <div class="a2-kv-row">
                            <div class="a2-kv-key">Created</div>
                            <div class="a2-kv-val" dir="ltr">
                                {{ $a->created_at ? $a->created_at->format('Y-m-d H:i') : '—' }}
                            </div>
                        </div>

                        <div class="a2-kv-row">
                            <div class="a2-kv-key">Updated</div>
                            <div class="a2-kv-val" dir="ltr">
                                {{ $a->updated_at ? $a->updated_at->format('Y-m-d H:i') : '—' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
