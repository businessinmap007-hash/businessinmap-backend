@extends('admin-v2.layouts.master')

@section('title', 'Price Rules')
@section('body_class', 'admin-v2-bookable-price-rules')

@section('content')
@include('admin-v2.bookable-items.partials.tabs', ['item'=>$item])

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">Price Rules</h1>
            <div class="a2-page-subtitle">
                {{ $item->title }} — إدارة قواعد التسعير
            </div>
        </div>

        <div class="a2-page-actions">
            <a class="a2-btn a2-btn-ghost" href="{{ route('admin.bookable-items.index') }}">رجوع</a>
            <a class="a2-btn a2-btn-ghost" href="{{ route('admin.bookable-items.calendar', $item) }}">Calendar</a>
            <a class="a2-btn a2-btn-primary" href="{{ route('admin.bookable-items.price-rules.create', $item) }}">
                + إضافة Rule
            </a>
        </div>
    </div>

    <div class="a2-card">
        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Rule Type</th>
                        <th>Price Type</th>
                        <th>Price Value</th>
                        <th>Range</th>
                        <th>Priority</th>
                        <th>Active</th>
                        <th>Actions</th>
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
                            <td>{{ $rule->rule_type ?: '-' }}</td>
                            <td>{{ $rule->price_type ?: '-' }}</td>
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
                                    <form method="POST"
                                          action="{{ route('admin.bookable-items.price-rules.destroy', [$item, $rule]) }}"
                                          onsubmit="return confirm('حذف قاعدة التسعير؟')">
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

        <div class="a2-mt-12">
            {{ $rules->links() }}
        </div>
    </div>
</div>
@endsection
