@props([
  'path' => null,
  'alt' => '',
  'size' => 48,
  'fit' => 'cover',
  'radius' => '12px',
  'circle' => false,
  'bg' => '#f3f4f6',
  'border' => '1px solid var(--border)',
  'placeholder' => 'No image',
])

@php
  $p = ltrim((string)($path ?? ''), '/');

  $src = null;

  if ($p !== '') {
    if (str_starts_with($p, 'http://') || str_starts_with($p, 'https://')) {
      $src = $p;
    } else {
      if (!str_starts_with($p, 'files/uploads/')) {
        if (str_starts_with($p, 'uploads/')) {
          $p = 'files/' . $p; // uploads/... -> files/uploads/...
        } elseif (!str_contains($p, '/')) {
          $p = 'files/uploads/' . $p; // filename -> files/uploads/filename
        }
      }
      $src = asset($p);
    }
  }

  $w = (int)$size;
  $r = $circle ? '999px' : (string)$radius;
@endphp

<div style="
  width: {{ $w }}px;
  height: {{ $w }}px;
  border-radius: {{ $r }};
  overflow: hidden;
  background: {{ $bg }};
  border: {{ $border }};
  display:flex;
  align-items:center;
  justify-content:center;
">
  @if($src)
    <img src="{{ $src }}" alt="{{ $alt }}">
  @else
    <span style="font-size:12px;color:var(--muted);">{{ $placeholder }}</span>
  @endif
</div>
