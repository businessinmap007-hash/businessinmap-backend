@extends('business.layouts.master')

@section('title', 'تعديل صنف')

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">تعديل صنف</h1>
        <div class="a2-page-subtitle">{{ $row->name_ar ?: $row->name_en }}</div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('business.menu.index') }}" class="a2-btn a2-btn-ghost">رجوع</a>
    </div>
</div>

@if(session('success'))
    <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
@endif

<form method="POST" action="{{ route('business.menu.update', $row->id) }}">
    @csrf
    @method('PUT')
    @include('business.menu._form', ['row' => $row])
</form>

{{-- ───────── الأحجام (variants) ───────── --}}
<div class="a2-card a2-card--section" style="margin-top:20px;">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">الأحجام / الخيارات</div>
            <div class="a2-card-sub">حجم أو خيار للصنف (صغير/وسط/كبير). سعر مباشر أو فرق عن السعر الأساسي.</div>
        </div>
    </div>

    <table class="a2-table">
        <thead><tr><th>النوع</th><th>الاسم</th><th>السعر</th><th>فرق السعر</th><th>افتراضي</th><th>نشط</th><th></th></tr></thead>
        <tbody>
        @forelse($row->variants as $v)
            <tr>
                <form method="POST" action="{{ route('business.menu.variants.update', [$row->id, $v->id]) }}">
                    @csrf @method('PUT')
                    <td><input class="a2-input" name="type" value="{{ $v->type }}" required></td>
                    <td><input class="a2-input" name="name_ar" value="{{ $v->name_ar }}" required></td>
                    <td><input class="a2-input" name="price" value="{{ $v->price }}" inputmode="decimal" placeholder="—" style="width:90px"></td>
                    <td><input class="a2-input" name="price_delta" value="{{ $v->price_delta }}" inputmode="decimal" placeholder="—" style="width:90px"></td>
                    <td><input type="checkbox" name="is_default" value="1" @checked($v->is_default)></td>
                    <td><input type="checkbox" name="is_active" value="1" @checked($v->is_active)></td>
                    <td>
                        <button class="a2-btn a2-btn-sm a2-btn-primary" type="submit">حفظ</button>
                </form>
                        <form method="POST" action="{{ route('business.menu.variants.destroy', [$row->id, $v->id]) }}" style="display:inline" onsubmit="return confirm('حذف هذا الحجم؟')">
                            @csrf @method('DELETE')
                            <button class="a2-btn a2-btn-sm a2-btn-ghost" type="submit">حذف</button>
                        </form>
                    </td>
            </tr>
        @empty
            <tr><td colspan="7" class="a2-muted">لا أحجام بعد.</td></tr>
        @endforelse
            {{-- add new variant --}}
            <tr>
                <form method="POST" action="{{ route('business.menu.variants.store', $row->id) }}">
                    @csrf
                    <td><input class="a2-input" name="type" placeholder="size" required></td>
                    <td><input class="a2-input" name="name_ar" placeholder="كبير" required></td>
                    <td><input class="a2-input" name="price" inputmode="decimal" placeholder="سعر" style="width:90px"></td>
                    <td><input class="a2-input" name="price_delta" inputmode="decimal" placeholder="+فرق" style="width:90px"></td>
                    <td><input type="checkbox" name="is_default" value="1"></td>
                    <td><input type="checkbox" name="is_active" value="1" checked></td>
                    <td><button class="a2-btn a2-btn-sm a2-btn-primary" type="submit">إضافة</button></td>
                </form>
            </tr>
        </tbody>
    </table>
</div>

{{-- ───────── الإضافات (extras) ───────── --}}
<div class="a2-card a2-card--section" style="margin-top:20px;">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">الإضافات</div>
            <div class="a2-card-sub">إضافات اختيارية للصنف (جبنة زيادة، صوص…) بسعر لكل إضافة.</div>
        </div>
    </div>

    <table class="a2-table">
        <thead><tr><th>المجموعة</th><th>الاسم</th><th>السعر</th><th>أقصى كمية</th><th>نشط</th><th></th></tr></thead>
        <tbody>
        @forelse($row->extras as $x)
            <tr>
                <form method="POST" action="{{ route('business.menu.extras.update', [$row->id, $x->id]) }}">
                    @csrf @method('PUT')
                    <td><input class="a2-input" name="group_key" value="{{ $x->group_key }}" placeholder="—" style="width:110px"></td>
                    <td><input class="a2-input" name="name_ar" value="{{ $x->name_ar }}" required></td>
                    <td><input class="a2-input" name="price" value="{{ $x->price }}" inputmode="decimal" style="width:90px" required></td>
                    <td><input class="a2-input" name="max_qty" value="{{ $x->max_qty }}" type="number" min="1" max="99" style="width:70px"></td>
                    <td><input type="checkbox" name="is_active" value="1" @checked($x->is_active)></td>
                    <td>
                        <button class="a2-btn a2-btn-sm a2-btn-primary" type="submit">حفظ</button>
                </form>
                        <form method="POST" action="{{ route('business.menu.extras.destroy', [$row->id, $x->id]) }}" style="display:inline" onsubmit="return confirm('حذف هذه الإضافة؟')">
                            @csrf @method('DELETE')
                            <button class="a2-btn a2-btn-sm a2-btn-ghost" type="submit">حذف</button>
                        </form>
                    </td>
            </tr>
        @empty
            <tr><td colspan="6" class="a2-muted">لا إضافات بعد.</td></tr>
        @endforelse
            {{-- add new extra --}}
            <tr>
                <form method="POST" action="{{ route('business.menu.extras.store', $row->id) }}">
                    @csrf
                    <td><input class="a2-input" name="group_key" placeholder="اختياري" style="width:110px"></td>
                    <td><input class="a2-input" name="name_ar" placeholder="جبنة زيادة" required></td>
                    <td><input class="a2-input" name="price" inputmode="decimal" placeholder="سعر" style="width:90px" required></td>
                    <td><input class="a2-input" name="max_qty" type="number" min="1" max="99" value="1" style="width:70px"></td>
                    <td><input type="checkbox" name="is_active" value="1" checked></td>
                    <td><button class="a2-btn a2-btn-sm a2-btn-primary" type="submit">إضافة</button></td>
                </form>
            </tr>
        </tbody>
    </table>
</div>
@endsection
