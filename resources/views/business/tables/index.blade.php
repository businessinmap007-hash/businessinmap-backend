@extends('business.layouts.master')

@section('title', 'طاولات المطعم')

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">طاولات المطعم</h1>
        <div class="a2-page-subtitle">ملصق QR لكل طاولة — مسحه يفتح طلب الطاولة (سلة جماعية) تلقائياً.</div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('business.tables.print') }}" target="_blank" class="a2-btn a2-btn-ghost">طباعة الرموز</a>
    </div>
</div>

@if(session('success'))
    <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
@endif

<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">إضافة طاولة</div>
            <div class="a2-card-sub">أعطِ الطاولة اسماً (مثل «طاولة 5» أو «الشرفة»).</div>
        </div>
    </div>
    <form method="POST" action="{{ route('business.tables.store') }}">
        @csrf
        <div class="a2-form-grid" style="grid-template-columns:1fr auto;align-items:end;gap:12px;">
            <div class="a2-form-group">
                <label class="a2-label" for="label">اسم الطاولة <span class="a2-danger">*</span></label>
                <input class="a2-input @error('label') a2-input-error @enderror" id="label" name="label" value="{{ old('label') }}" placeholder="طاولة 1" required>
                @error('label')<div class="a2-field-error">{{ $message }}</div>@enderror
            </div>
            <div class="a2-form-group">
                <button type="submit" class="a2-btn a2-btn-primary">إضافة</button>
            </div>
        </div>
    </form>
</div>

<div class="a2-card">
    <div class="a2-table-wrap">
        <table class="a2-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>الطاولة</th>
                    <th>الرمز</th>
                    <th>الحالة</th>
                    <th class="a2-text-right">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>{{ $row->id }}</td>
                        <td class="a2-fw-900">{{ $row->label }}</td>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <img src="{{ route('table.qr', $row->token, false) }}" alt="QR" width="56" height="56" style="border:1px solid var(--a2-line,#e6e9ef);border-radius:8px;background:#fff;">
                                <a href="{{ route('table.scan.web', $row->token, false) }}" target="_blank" class="a2-btn a2-btn-sm a2-btn-ghost">فتح الرابط</a>
                            </div>
                        </td>
                        <td>
                            @if($row->is_active)
                                <span class="a2-pill a2-pill-success">نشطة</span>
                            @else
                                <span class="a2-pill a2-pill-gray">غير نشطة</span>
                            @endif
                        </td>
                        <td class="a2-text-right">
                            <div class="a2-inline-actions" style="align-items:center;">
                                <form method="POST" action="{{ route('business.tables.update', $row->id) }}" style="display:flex;gap:8px;align-items:center;">
                                    @csrf
                                    @method('PUT')
                                    <input class="a2-input" name="label" value="{{ $row->label }}" style="width:130px;padding:7px 9px;" required>
                                    <label class="a2-check" style="white-space:nowrap;"><input type="checkbox" name="is_active" value="1" @checked($row->is_active)> <span>نشطة</span></label>
                                    <button class="a2-btn a2-btn-sm a2-btn-ghost" type="submit">حفظ</button>
                                </form>
                                <form method="POST" action="{{ route('business.tables.destroy', $row->id) }}" onsubmit="return confirm('حذف هذه الطاولة؟ سيتوقف رمزها عن العمل.');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="a2-btn a2-btn-sm a2-btn-danger" type="submit">حذف</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="a2-empty">لا طاولات بعد. أضف طاولة لتوليد رمز QR لها.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if(method_exists($rows, 'links'))
        <div class="a2-pagination">{{ $rows->links() }}</div>
    @endif
</div>
@endsection
