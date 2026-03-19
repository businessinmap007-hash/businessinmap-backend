@extends('admin-v2.layouts.master')

@section('title', 'Price Rules')
@section('body_class', 'admin-v2-bookable-price-rules')

@section('content')
@include('admin-v2.bookable-items.partials.tabs', ['item' => $item])

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">قواعد التسعير</h1>
            <div class="a2-page-subtitle">
                {{ $item->title }} — إدارة التسعير الديناميكي لهذا العنصر
            </div>
        </div>

        <div class="a2-page-actions">
            <a class="a2-btn a2-btn-ghost" href="{{ route('admin.bookable-items.index') }}">رجوع</a>

            <a class="a2-btn a2-btn-ghost" href="{{ route('admin.bookable-items.calendar', $item) }}">
                التقويم
            </a>

            <a class="a2-btn a2-btn-primary" href="{{ route('admin.bookable-items.price-rules.create', $item) }}">
                + إضافة Rule
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="a2-card a2-card--section">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">ملخص العنصر</div>
                <div class="a2-card-sub">العنصر الذي يتم تطبيق قواعد التسعير عليه</div>
            </div>
        </div>

        <div class="a2-form-grid">
            <div><strong>Title:</strong> {{ $item->title ?: '—' }}</div>
            <div><strong>Type:</strong> <span dir="ltr">{{ $item->item_type ?: '—' }}</span></div>
            <div><strong>Code:</strong> <span dir="ltr">{{ $item->code ?: '—' }}</span></div>
            <div><strong>Base Price:</strong> {{ number_format((float) ($item->price ?? 0), 2) }}</div>
        </div>
    </div>

    <div class="a2-card" style="margin-top:16px;">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">قائمة قواعد التسعير</div>
                <div class="a2-card-sub">
                    @if(method_exists($rules, 'total'))
                        إجمالي السجلات: {{ $rules->total() }}
                    @else
                        عرض قواعد السعر المرتبطة بهذا العنصر
                    @endif
                </div>
            </div>
        </div>

        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th style="width:80px;">ID</th>
                        <th>Title</th>
                        <th style="width:140px;">Rule Type</th>
                        <th style="width:130px;">Price Type</th>
                        <th style="width:160px;">Price Value</th>
                        <th style="width:220px;">Range</th>
                        <th style="width:100px;">Priority</th>
                        <th style="width:100px;">Active</th>
                        <th style="width:120px;">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($rules as $rule)
                        <tr>
                            <td>{{ $rule->id }}</td>

                            <td>
                                <span class="a2-clip" title="{{ $rule->title }}">
                                    {{ $rule->title ?: '-' }}
                                </span>
                            </td>

                            <td>
                                <span dir="ltr">{{ $rule->rule_type ?: '-' }}</span>
                            </td>

                            <td>
                                <span dir="ltr">{{ $rule->price_type ?: '-' }}</span>
                            </td>

                            <td dir="ltr">
                                {{ number_format((float) $rule->price_value, 2) }}
                                {{ $rule->currency ?: 'EGP' }}
                            </td>

                            <td dir="ltr">
                                {{ optional($rule->start_date)->format('Y-m-d') ?: '-' }}
                                →
                                {{ optional($rule->end_date)->format('Y-m-d') ?: '-' }}
                            </td>

                            <td>{{ $rule->priority ?? 100 }}</td>

                            <td>
                                @if($rule->is_active)
                                    <span class="a2-pill a2-pill-active">Yes</span>
                                @else
                                    <span class="a2-pill a2-pill-gray">No</span>
                                @endif
                            </td>

                            <td>
                                <div class="a2-actions">
                                    <a class="a2-btn a2-btn-ghost a2-btn-sm"href="{{ route('admin.bookable-items.price-rules.edit', [$item, $rule]) }}">Edit</a>
                                    <form method="POST"
                                          action="{{ route('admin.bookable-items.price-rules.destroy', [$item, $rule]) }}"
                                          onsubmit="return confirm('حذف قاعدة التسعير؟')"
                                          style="margin:0;">
                                        @csrf
                                        @method('DELETE')

                                        <button class="a2-btn a2-btn-danger a2-btn-sm" type="submit">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="a2-empty-cell">لا توجد قواعد تسعير</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(method_exists($rules, 'links'))
            <div class="a2-mt-12">
                {{ $rules->links() }}
            </div>
        @endif
    </div>
</div>
@endsection