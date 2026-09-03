<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $title = $post->title ?: ($post->user->name ?? 'BIM');
        $description = \Illuminate\Support\Str::limit(strip_tags((string) $post->body), 200);
        $firstImage = $post->images->first()?->image ?? $post->image;
        $ogImage = $firstImage ? asset($firstImage) : ($post->user->logo ? asset($post->user->logo) : null);
    @endphp
    <title>{{ $title }} — BIM</title>
    <meta name="description" content="{{ $description }}">

    {{-- Open Graph — what WhatsApp/Facebook/Telegram read to build the shared card. --}}
    <meta property="og:type" content="article">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ url()->current() }}">
    @if($ogImage)
        <meta property="og:image" content="{{ $ogImage }}">
    @endif
    <meta name="twitter:card" content="{{ $ogImage ? 'summary_large_image' : 'summary' }}">

    <style>
        body {
            font-family: -apple-system, 'Segoe UI', Tahoma, Arial, sans-serif;
            background: #0f172a;
            color: #f1f5f9;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
        }
        .card {
            max-width: 480px;
            width: 100%;
            margin: 24px 16px;
        }
        .author {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
        }
        .author img {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            background: #1e293b;
        }
        .author .name {
            font-weight: 700;
            font-size: 14px;
        }
        .photo {
            width: 100%;
            border-radius: 12px;
            display: block;
            margin-bottom: 12px;
        }
        h1 {
            font-size: 18px;
            margin: 0 0 8px;
        }
        p {
            font-size: 15px;
            line-height: 1.6;
            white-space: pre-wrap;
        }
        .hint {
            margin-top: 24px;
            padding-top: 16px;
            border-top: 1px solid #1e293b;
            color: #94a3b8;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="author">
            @if($post->user->logo ?? $post->user->image ?? null)
                <img src="{{ asset($post->user->logo ?: $post->user->image) }}" alt="">
            @endif
            <span class="name">{{ $post->user->name ?? '' }}</span>
        </div>

        @if($ogImage)
            <img class="photo" src="{{ $ogImage }}" alt="">
        @endif

        @if($post->title)
            <h1>{{ $post->title }}</h1>
        @endif
        <p>{{ $post->body }}</p>

        <div class="hint">افتح تطبيق BIM لعرض المنشور والتعليق عليه.</div>
    </div>
</body>
</html>
