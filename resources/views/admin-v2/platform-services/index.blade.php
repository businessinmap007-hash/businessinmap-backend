@extends('admin-v2.layouts.master')
@section('title','Platform Services')
@section('body_class','admin-v2-platform-services')
@section('topbar_title','Platform Services')

@section('content')
<div class="a2-page">

    <div class="a2-card" style="padding:14px;margin-bottom:12px;">
        <div class="a2-header" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
            <div>
                <div class="a2-title">Platform Services</div>
                <div class="a2-hint">الخدمات الأساسية للنظام + رسوم المنصة + سياسة الديبوزت</div>
            </div>

            <a class="a2-btn a2-btn-primary" href="{{ route('admin.platform-services.create') }}">
                + إضافة
            </a>
        </div>
    </div>

    <div class="a2-card" style="padding:14px;margin-bottom:12px;">
        <form method="GET" style="display:grid;grid-template-columns:1.2fr .6fr auto;gap:10px;align-items:end;">
            <div>
                <label class="a2-label">بحث</label>
                <input class="a2-input" type="text" name="q" value="{{ $q ?? '' }}" placeholder="key / name_ar / name_en">
            </div>

            <div>
                <label class="a2-label">Active</label>
                <select class="a2-input" name="is_active">
                    <option value="">الكل</option>
                    <option value="1" @selected(request('is_active') === '1')>Yes</option>
                    <option value="0" @selected(request('is_active') === '0')>No</option>
                </select>
            </div>

            <div style="display:flex;gap:8px;">
                <button class="a2-btn a2-btn-primary" type="submit">بحث</button>
                <a class="a2-btn a2-btn-ghost" href="{{ route('admin.platform-services.index') }}">Reset</a>
            </div>
        </form>
    </div>

    <div class="a2-card" style="padding:14px;">
        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Key</th>
                        <th>الاسم</th>
                        <th>Active</th>
                        <th>Deposit</th>
                        <th>Max %</th>
                        <th>Fee Type</th>
                        <th>Fee Value</th>
                        <th>Rules</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($rows as $r)
                    <tr>
                        <td>{{ $r->id }}</td>

                        <td>
                            <code>{{ $r->key }}</code>
                        </td>

                        <td>
                            <div style="font-weight:700;">{{ $r->name_ar }}</div>
                            <div class="a2-hint">{{ $r->name_en ?: '-' }}</div>
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

                        <td>{{ $r->fee_type ?: '-' }}</td>

                        <td>
                            {{ $r->fee_value !== null ? number_format((float)$r->fee_value, 2) : '-' }}
                        </td>

                        <td>
                            @if(!empty($r->rules))
                                <span class="a2-hint">JSON</span>
                            @else
                                -
                            @endif
                        </td>

                        <td class="a2-actions">
                            <a class="a2-btn a2-btn-ghost" href="{{ route('admin.platform-services.edit', $r) }}">Edit</a>

                            <form method="POST" action="{{ route('admin.platform-services.destroy', $r) }}" style="display:inline;" onsubmit="return confirm('Delete?')">
                                @csrf
                                @method('DELETE')
                                <button class="a2-btn a2-btn-danger" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" style="text-align:center;">لا توجد بيانات</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:12px;">
            {{ $rows->links() }}
        </div>
    </div>
</div>
@endsection