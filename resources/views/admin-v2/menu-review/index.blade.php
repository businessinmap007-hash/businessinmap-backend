@extends('admin-v2.layouts.master')

@section('title', 'مراجعة المنيو')
@section('body_class', 'admin-v2-menu-review')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('مراجعة المنيو') }}</h1>
            <div class="a2-page-subtitle">
                {{ __('منيو نشاطٍ واحد كاملًا — القسم، ثم البند، ثم أصنافه. والبندُ الفارغ هو المقصود: ما يستطيع بيعه ولم يعرضه بعد.') }}
            </div>
        </div>
    </div>

    <div class="a2-card">
        <form method="GET" action="{{ route('admin.menu-review.index', [], false) }}" class="a2-filterbar">
            <select class="a2-select a2-filter-md" name="business_id"
                    data-remote-url="{{ route('admin.business-lookup', [], false) }}"
                    data-placeholder="{{ __('ابحث باسم النشاط أو رقمه #') }}">
                <option value="0">{{ __('اختر نشاطًا') }}</option>
                @if($business)
                    <option value="{{ $business->id }}" selected>#{{ $business->id }} — {{ $business->name }}</option>
                @endif
            </select>

            <button class="a2-btn a2-btn-primary" type="submit">{{ __('عرض') }}</button>
        </form>
    </div>

    @if(! $business)
        {{-- بلا نشاطٍ مختار: من عنده منيو أصلًا، والأكبرُ أوّلًا. --}}
        <div class="a2-card">
            <div class="a2-card-head">
                <div class="a2-card-title">{{ __('الأنشطة التي لديها منيو') }}</div>
            </div>

            <div class="a2-table-wrap">
                <table class="a2-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ __('النشاط') }}</th>
                            <th>{{ __('التصنيف') }}</th>
                            <th>{{ __('الأصناف') }}</th>
                            <th>{{ __('المتاح') }}</th>
                            <th class="a2-text-right">{{ __('إجراءات') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($withMenus as $row)
                            <tr>
                                <td>{{ $row->id }}</td>
                                <td class="a2-fw-900">{{ $row->name }}</td>
                                <td><span class="a2-muted">{{ $row->child ?: '—' }}</span></td>
                                <td class="a2-fw-900">{{ (int) $row->items }}</td>
                                <td>{{ (int) $row->active_items }}</td>
                                <td class="a2-text-right">
                                    <a class="a2-btn a2-btn-sm a2-btn-ghost"
                                       href="{{ route('admin.menu-review.index', ['business_id' => $row->id], false) }}">
                                        {{ __('مراجعة') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="a2-empty">{{ __('لا يوجد نشاطٌ كتب منيو بعد.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @else
        @php $totals = $outline['totals']; @endphp

        <div class="mr-totals">
            <div class="mr-stat">
                <div class="mr-stat-n">{{ $totals['items'] }}</div>
                <div class="mr-stat-l">{{ __('صنف') }}</div>
            </div>
            <div class="mr-stat">
                <div class="mr-stat-n">{{ $totals['sections'] }}</div>
                <div class="mr-stat-l">{{ __('قسم') }}</div>
            </div>
            <div class="mr-stat">
                <div class="mr-stat-n">{{ $totals['filled_bands'] }} / {{ $totals['bands'] }}</div>
                <div class="mr-stat-l">{{ __('بند معروض') }}</div>
            </div>
            <div class="mr-stat {{ $totals['empty_bands'] > 0 ? 'is-warn' : '' }}">
                <div class="mr-stat-n">{{ $totals['empty_bands'] }}</div>
                <div class="mr-stat-l">{{ __('بند فارغ') }}</div>
            </div>
            @if($totals['unplaced'] > 0)
                <div class="mr-stat is-warn">
                    <div class="mr-stat-n">{{ $totals['unplaced'] }}</div>
                    <div class="mr-stat-l">{{ __('بلا بند') }}</div>
                </div>
            @endif
        </div>

        @foreach($outline['sections'] as $section)
            <div class="a2-card mr-section">
                <div class="a2-card-head">
                    <div>
                        <div class="a2-card-title">
                            {{ $section['label'] }}
                            @if($section['source'] === 'section')
                                <span class="a2-pill a2-pill-gray">{{ __('قسم كتبه التاجر') }}</span>
                                @if(! $section['is_active'])
                                    <span class="a2-pill a2-pill-gray">{{ __('مغلق') }}</span>
                                @endif
                            @elseif($section['source'] === 'item_type')
                                <span class="a2-pill a2-pill-gray">{{ __('بلا مفردة') }}</span>
                            @elseif($section['source'] === 'unplaced')
                                <span class="a2-pill a2-pill-danger">{{ __('غير مصنّف') }}</span>
                            @endif
                        </div>
                        <div class="a2-card-sub">
                            {{ $section['items'] }} {{ __('صنف') }} ·
                            {{ $section['filled_bands'] }} {{ __('بند معروض') }}
                            @if($section['empty_bands'] > 0)
                                · <span class="mr-warn">{{ $section['empty_bands'] }} {{ __('فارغ') }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="mr-bands">
                    @foreach($section['bands'] as $band)
                        <div class="mr-band {{ $band['count'] === 0 ? 'is-empty' : '' }}">
                            <div class="mr-band-head">
                                <span class="mr-band-name">{{ $band['label'] }}</span>
                                <span class="mr-band-count">{{ $band['count'] }}</span>
                                @if($band['unexpected'])
                                    <span class="a2-pill a2-pill-danger">{{ __('خارج مفردات نشاطه') }}</span>
                                @endif
                            </div>

                            @if($band['count'] === 0)
                                <div class="mr-band-empty">{{ __('لا يعرض شيئًا تحت هذا البند.') }}</div>
                            @else
                                <ul class="mr-items">
                                    @foreach($band['items'] as $item)
                                        <li>
                                            <a href="{{ route('admin.menu-items.edit', $item->id, false) }}">
                                                {{ $item->name_ar ?: $item->name_en ?: ('#' . $item->id) }}
                                            </a>
                                            {{-- المُوصِّفات وحدها. `offeringLabel()` يبدأ بالسطر،
                                                 والسطرُ هو اسمُ البند فوقه — فكتابته تكرارٌ يقرأ
                                                 «بيتزا — بيتزا». --}}
                                            @php $mods = $item->modifierOptions(); @endphp
                                            @if($mods->isNotEmpty())
                                                <span class="a2-muted">— {{ $mods->map(fn ($o) => $o->name_ar ?: $o->name_en)->implode(' · ') }}</span>
                                            @endif
                                            <span class="mr-price">
                                                {{ number_format((float) $item->base_price, 2) }}
                                                @if($unit = $item->priceUnitLabel())
                                                    <small>/ {{ $unit }}</small>
                                                @endif
                                            </span>
                                            @if(! $item->is_active)
                                                <span class="a2-pill a2-pill-gray">{{ __('غير متاح') }}</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    @endif
</div>
@endsection

@push('styles')
<style>
    .mr-totals { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 14px; }
    .mr-stat { background: var(--a2-card, #fff); border: 1px solid rgba(0,0,0,.08); border-radius: 12px; padding: 10px 16px; min-width: 110px; }
    .mr-stat-n { font-size: 20px; font-weight: 900; }
    .mr-stat-l { font-size: 12px; opacity: .7; }
    .mr-stat.is-warn .mr-stat-n { color: #b45309; }
    .mr-warn { color: #b45309; font-weight: 700; }

    .mr-bands { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 10px; }
    .mr-band { border: 1px solid rgba(0,0,0,.08); border-radius: 12px; padding: 10px 12px; }
    .mr-band.is-empty { background: rgba(0,0,0,.02); border-style: dashed; }
    .mr-band-head { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
    .mr-band-name { font-weight: 900; }
    .mr-band-count { font-size: 12px; background: rgba(0,0,0,.06); border-radius: 999px; padding: 1px 8px; }
    .mr-band-empty { font-size: 12px; opacity: .6; }
    .mr-items { margin: 0; padding-inline-start: 16px; }
    .mr-items li { margin-bottom: 4px; }
    .mr-price { font-weight: 700; margin-inline-start: 6px; }
</style>
@endpush
