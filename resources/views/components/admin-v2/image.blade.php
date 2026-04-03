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
  'debug' => false,
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
          $p = 'files/' . $p;
        } elseif (!str_contains($p, '/')) {
          $p = 'files/uploads/' . $p;
        }
      }
      $src = asset($p);
    }
  }

  $raw = (string)($path ?? '');
  $w = (int)$size;
  $r = $circle ? '999px' : (string)$radius;
@endphp

<div style="display:inline-flex;flex-direction:column;gap:6px;align-items:flex-start;">
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
      <img
        src="{{ $src }}"
        alt="{{ $alt }}"
        style="width:100%;height:100%;object-fit:{{ $fit }};display:block;"
        @if($debug)
          onerror="this.closest('div').nextElementSibling.style.display='block';"
        @endif
      >
    @else
      <span style="font-size:12px;color:#667085;">{{ $placeholder }}</span>
    @endif
  </div>

  @if($debug)
    <div style="display:none;width:min(420px,90vw);font-size:12px;line-height:1.6;padding:8px 10px;border:1px solid #fecaca;background:#fef2f2;color:#b42318;border-radius:10px;">
      <div><strong>Image debug</strong></div>
      <div><strong>raw:</strong> {{ $raw !== '' ? $raw : 'NULL/EMPTY' }}</div>
      <div><strong>resolved:</strong> {{ $src ?: 'NULL' }}</div>
    </div>

    <div style="width:min(420px,90vw);font-size:12px;line-height:1.6;padding:8px 10px;border:1px solid #e5e7eb;background:#fff;border-radius:10px;color:#344054;">
      <div><strong>Image debug</strong></div>
      <div><strong>raw:</strong> {{ $raw !== '' ? $raw : 'NULL/EMPTY' }}</div>
      <div><strong>resolved:</strong> {{ $src ?: 'NULL' }}</div>
    </div>
  @endif
</div>