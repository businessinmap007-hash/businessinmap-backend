@extends('admin-v2.layouts.master')

@section('title','Platform Services')
@section('body_class','admin-v2 admin-v2-platform-services-index')
@section('topbar_title','Platform Services')

@section('content')
<div class="a2-page">

    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">Platform Services</h1>
            <div class="a2-page-subtitle">
                الخدمات الأساسية للنظام + سياسة الديبوزت + الربط مع الأقسام الفرعية ورسوم التنفيذ.
            </div>
        </div>

        <div class="a2-page-actions">
            <a class="a2-btn a2-btn-primary" href="{{ route('admin.platform-services.create') }}">
                + إضافة خدمة
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="a2-alert a2-alert-danger">
            {{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">
            <ul style="margin:0;padding-inline-start:18px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="a2-card a2-card--soft a2-mb-16">
        <div class="a2-section-title">ملاحظات تشغيلية</div>
        <div class="a2-section-subtitle">
            الخدمة هنا هي تعريف عام مثل booking / delivery / menu.
            إتاحة الخدمة لقسم فرعي تتم من
            <span dir="ltr">category_platform_services</span>
            أما رسوم العميل والبزنس فتتم من
            <span dir="ltr">category_child_service_fees</span>.
        </div>
    </div>

    <div class="a2-card a2-mb-16">
        <form method="GET" class="a2-filterbar">
            <div class="a2-filter-search">
                <label class="a2-label">بحث</label>
                <input
                    class="a2-input"
                    type="text"
                    name="q"
                    value="{{ $q ?? '' }}"
                    placeholder="key / name_ar / name_en"
                >
            </div>

            <div class="a2-filter-sm">
                <label class="a2-label">Active</label>
                <select class="a2-input" name="is_active">
                    <option value="">الكل</option>
                    <option value="1" @selected((string)($isActive ?? '') === '1')>Yes</option>
                    <option value="0" @selected((string)($isActive ?? '') === '0')>No</option>
                </select>
            </div>

            <div class="a2-filter-actions">
                <button class="a2-btn a2-btn-primary" type="submit">بحث</button>
                <a class="a2-btn a2-btn-ghost" href="{{ route('admin.platform-services.index') }}">Reset</a>
            </div>
        </form>
    </div>

    <div class="a2-card">
        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th style="min-width:70px;">ID</th>
                        <th style="min-width:120px;">Key</th>
                        <th style="min-width:190px;">الاسم</th>
                        <th style="min-width:90px;">Active</th>
                        <th style="min-width:110px;">Deposit</th>
                        <th style="min-width:90px;">Max %</th>
                        <th style="min-width:120px;">Legacy Fee</th>
                        <th style="min-width:150px;">Category Links</th>
                        <th style="min-width:140px;">Service Fees</th>
                        <th style="min-width:90px;">Rules</th>
                        <th style="min-width:170px;">Actions</th>
                    </tr>
                </thead>

                <tbody>
                @forelse($rows as $r)
                    @php
                        $serviceName = $r->name_ar ?: ($r->name_en ?: ($r->key ?: ('#' . $r->id)));

                        $categoryLinksCount = (int) ($r->category_platform_services_count ?? 0);
                        $activeCategoryLinksCount = (int) ($r->active_category_platform_services_count ?? 0);

                        $serviceFeesCount = (int) ($r->category_child_service_fees_count ?? 0);
                        $activeServiceFeesCount = (int) ($r->active_category_child_service_fees_count ?? 0);

                        $isUsed = ($categoryLinksCount + $serviceFeesCount) > 0;
                    @endphp

                    <tr>
                        <td>
                            <div class="a2-fw-900">{{ $r->id }}</div>
                        </td>

                        <td>
                            <code>{{ $r->key }}</code>
                        </td>

                        <td class="a2-text-right">
                            <div class="a2-fw-900">{{ $serviceName }}</div>
                            <div class="a2-muted a2-mt-8">{{ $r->name_en ?: '-' }}</div>
                        </td>

                        <td>
                            @if($r->is_active)
                                <span class="a2-badge a2-badge-success">Yes</span>
                            @else
                                <span class="a2-badge a2-badge-muted">No</span>
                            @endif
                        </td>

                        <td>
                            @if($r->supports_deposit)
                                <span class="a2-badge a2-badge-success">Yes</span>
                            @else
                                <span class="a2-badge a2-badge-muted">No</span>
                            @endif
                        </td>

                        <td>{{ (int) $r->max_deposit_percent }}%</td>

                        <td>
                            @if($r->fee_type && (float) $r->fee_value > 0)
                                <div class="a2-fw-900">{{ $r->fee_type }}</div>
                                <div class="a2-muted a2-mt-8">
                                    {{ number_format((float)$r->fee_value, 2) }}
                                </div>
                            @else
                                <span class="a2-muted">—</span>
                            @endif
                        </td>

                        <td>
                            <div>
                                <span class="a2-pill a2-pill-gray">
                                    Total: {{ $categoryLinksCount }}
                                </span>
                            </div>

                            <div class="a2-mt-8">
                                <span class="a2-pill {{ $activeCategoryLinksCount > 0 ? 'a2-pill-success' : 'a2-pill-gray' }}">
                                    Active: {{ $activeCategoryLinksCount }}
                                </span>
                            </div>
                        </td>

                        <td>
                            <div>
                                <span class="a2-pill a2-pill-gray">
                                    Total: {{ $serviceFeesCount }}
                                </span>
                            </div>

                            <div class="a2-mt-8">
                                <span class="a2-pill {{ $activeServiceFeesCount > 0 ? 'a2-pill-success' : 'a2-pill-gray' }}">
                                    Active: {{ $activeServiceFeesCount }}
                                </span>
                            </div>
                        </td>

                        <td>
                            @if(!empty($r->rules))
                                <span class="a2-pill a2-pill-success">JSON</span>
                            @else
                                <span class="a2-muted">—</span>
                            @endif
                        </td>

                        <td class="a2-actions">
                            <a class="a2-btn a2-btn-ghost" href="{{ route('admin.platform-services.edit', $r) }}">
                                Edit
                            </a>

                            @if($isUsed)
                                <button
                                    class="a2-btn a2-btn-danger"
                                    type="button"
                                    disabled
                                    title="لا يمكن حذف خدمة مستخدمة. عطّلها بدلًا من حذفها."
                                >
                                    Delete
                                </button>
                            @else
                                <form
                                    method="POST"
                                    action="{{ route('admin.platform-services.destroy', $r) }}"
                                    style="display:inline;"
                                    onsubmit="return confirm('Delete?')"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button class="a2-btn a2-btn-danger" type="submit">
                                        Delete
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="a2-empty-cell">
                            لا توجد بيانات
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="a2-mt-16">
            {{ $rows->links() }}
        </div>
    </div>
</div>
@endsection