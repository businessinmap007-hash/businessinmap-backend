@extends('admin-v2.layouts.master')

@section('title', 'Service Branch Review')
@section('body_class', 'admin-v2 admin-v2-platform-service-item-groups-review')

@section('content')
@php
    $sections = [
        'unused' => [
            'title' => __('لا يصل إلى أي نشاط'),
            'note' => __('لا يسمح به إعداد أي ابن، فلا يراه تاجر أصلًا ولا يمكن أن يبيع من خلاله. هذه هي المجموعة المطلوبة للمراجعة اليدوية.'),
            'pill' => 'a2-pill-danger',
        ],
        'offered' => [
            'title' => __('معروض ولم يُستخدم بعد'),
            'note' => __('أبناء يسمحون به، لكن لا تاجر سعّر فيه صفًا ولا أدرج وحدة. قد يكون قسمًا لم يُملأ بعد، لا قسمًا ميتًا.'),
            'pill' => 'a2-pill-gray',
        ],
        'in_use' => [
            'title' => __('قيد الاستخدام'),
            'note' => __('يوجد صف مسعّر أو وحدة قابلة للحجز خلف هذا الفرع.'),
            'pill' => 'a2-pill-success',
        ],
    ];

    $displayName = function ($item) {
        $ar = trim((string) ($item->name_ar ?? ''));
        $en = trim((string) ($item->name_en ?? ''));
        return $ar !== '' ? $ar : ($en !== '' ? $en : ('#' . ($item->id ?? '')));
    };
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('مراجعة فروع الخدمات') }}</h1>
            <div class="a2-page-subtitle">
                {{ __('الفرع وعاء، فلا يُقاس بنفسه بل بما يحمله: فرع ← أنواع عناصر ← إعداد ابن يسمح بها ← تسعيرة تاجر أو وحدة. كل فرع مصنّف حسب أبعد نقطة وصلها.') }}
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.platform-service-item-groups.index') }}" class="a2-btn a2-btn-ghost">{{ __('كل الفروع') }}</a>
        </div>
    </div>

    <div class="a2-alert a2-alert-info">
        {{ __('هذه الشاشة للقراءة فقط. الفرع الذي لا يصل إلى أحد قد يكون ميتًا وقد يكون قسمًا لم يُفتح بعد، والفرق قرارك أنت.') }}
    </div>

    @foreach($sections as $key => $meta)
        @php $rows = $buckets[$key] ?? []; @endphp

        <div class="a2-card">
            <div class="a2-card-head">
                <h2 class="a2-card-title">
                    {{ $meta['title'] }}
                    <span class="a2-pill {{ $meta['pill'] }}">{{ count($rows) }}</span>
                </h2>
                <div class="a2-muted">{{ $meta['note'] }}</div>
            </div>

            <div class="a2-table-wrap">
                <table class="a2-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ __('الخدمة') }}</th>
                            <th>{{ __('الفرع') }}</th>
                            <th>{{ __('أنواع') }}</th>
                            <th>{{ __('إعدادات تسمح به') }}</th>
                            <th>{{ __('صفوف مسعّرة') }}</th>
                            <th>{{ __('وحدات') }}</th>
                            <th>{{ __('الحالة') }}</th>
                            <th class="a2-text-right">{{ __('إجراءات') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            @php $branch = $row['branch']; @endphp
                            <tr>
                                <td>{{ $branch->id }}</td>
                                <td>
                                    {{ $branch->service ? $displayName($branch->service) : '—' }}
                                    @if($branch->service && ! $branch->service->is_active)
                                        <span class="a2-pill a2-pill-gray">{{ __('خدمة معطّلة') }}</span>
                                    @endif
                                </td>
                                <td>
                                    {{ $displayName($branch) }}
                                    <div class="a2-muted">{{ $branch->key }}</div>
                                </td>
                                <td>
                                    {{ $row['types'] }}
                                    @if($row['types'] > 0)
                                        <div class="a2-muted">{{ $branch->itemTypes->take(4)->map($displayName)->implode('، ') }}{{ $branch->itemTypes->count() > 4 ? ' …' : '' }}</div>
                                    @endif
                                </td>
                                <td>{{ $row['configs'] }}</td>
                                <td>{{ $row['priced'] }}</td>
                                <td>{{ $row['units'] }}</td>
                                <td>
                                    <span class="a2-pill {{ $branch->is_active ? 'a2-pill-success' : 'a2-pill-gray' }}">
                                        {{ $branch->is_active ? __('مفعّل') : __('معطّل') }}
                                    </span>
                                </td>
                                <td class="a2-text-right">
                                    <a href="{{ route('admin.platform-service-item-groups.edit', $branch->id) }}" class="a2-btn a2-btn-sm a2-btn-ghost">{{ __('فتح') }}</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="a2-empty">{{ __('لا فروع في هذه المجموعة.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
</div>
@endsection
