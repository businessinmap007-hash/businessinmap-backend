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
        {{-- الرسمُ نفسه الذي يراه التاجر في لوحته — ملفٌ واحد، فلا تفترق
             «المراجعة» عن «المراجعة». --}}
        @include('shared.menu-outline', [
            'outline' => $outline,
            'editRoute' => fn ($id) => route('admin.menu-items.edit', $id, false),
        ])
    @endif
</div>
@endsection

