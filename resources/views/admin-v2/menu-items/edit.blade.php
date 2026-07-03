@extends('admin-v2.layouts.master')

@section('title', 'Edit Menu Item')
@section('body_class', 'admin-v2-menu-items-edit')

@section('content')
<div class="a2-page a2-page-narrow">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">تعديل عنصر منيو</h1>
            <div class="a2-page-subtitle">{{ $row->name_ar ?: ('#' . $row->id) }}</div>
        </div>

        <div class="a2-page-actions">
            <a class="a2-btn a2-btn-ghost" href="{{ route('admin.menu-items.index') }}">رجوع</a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success a2-mb-12">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger a2-mb-12">
            <div class="a2-fw-900 a2-mb-8">يوجد أخطاء</div>
            <ul class="a2-errors-list">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.menu-items.update', $row) }}">
        @csrf
        @method('PUT')

        @include('admin-v2.menu-items._form', [
            'row' => $row,
            'businesses' => $businesses,
            'submitLabel' => 'تحديث',
        ])
    </form>

    <div class="a2-card" style="margin-top:16px;">
        <h2 class="a2-section-title">Variants (مقاسات / أنواع)</h2>

        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Name</th>
                        <th>Price</th>
                        <th>Delta</th>
                        <th>Default</th>
                        <th>Status</th>
                        <th style="width:120px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($row->variants as $variant)
                    @php $variantFormId = 'variant-form-' . $variant->id; @endphp
                    <tr>
                        <td><input class="a2-input" form="{{ $variantFormId }}" type="text" name="type" value="{{ $variant->type }}" required></td>
                        <td><input class="a2-input" form="{{ $variantFormId }}" type="text" name="name_ar" value="{{ $variant->name_ar }}" required></td>
                        <td><input class="a2-input" form="{{ $variantFormId }}" type="number" step="0.01" name="price" value="{{ $variant->price }}"></td>
                        <td><input class="a2-input" form="{{ $variantFormId }}" type="number" step="0.01" name="price_delta" value="{{ $variant->price_delta }}"></td>
                        <td><input form="{{ $variantFormId }}" type="checkbox" name="is_default" value="1" @checked($variant->is_default)></td>
                        <td><input form="{{ $variantFormId }}" type="checkbox" name="is_active" value="1" @checked($variant->is_active)></td>
                        <td>
                            <form id="{{ $variantFormId }}" method="POST" action="{{ route('admin.menu-items.variants.update', [$row, $variant]) }}" style="display:none;">
                                @csrf
                                @method('PUT')
                            </form>
                            <button type="submit" form="{{ $variantFormId }}" class="a2-btn a2-btn-ghost a2-btn-sm">حفظ</button>

                            <form method="POST" action="{{ route('admin.menu-items.variants.destroy', [$row, $variant]) }}" onsubmit="return confirm('حذف الـ variant؟');" style="display:inline;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="a2-btn a2-btn-danger a2-btn-sm">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="a2-empty-cell">لا توجد variants</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <form method="POST" action="{{ route('admin.menu-items.variants.store', $row) }}" class="a2-form-grid" style="margin-top:12px;">
            @csrf
            <input class="a2-input" type="text" name="type" placeholder="النوع (size/color..)" required>
            <input class="a2-input" type="text" name="name_ar" placeholder="الاسم بالعربي" required>
            <input class="a2-input" type="number" step="0.01" name="price" placeholder="سعر مباشر">
            <input class="a2-input" type="number" step="0.01" name="price_delta" placeholder="فرق عن السعر الأساسي">
            <label class="a2-checkbox-label"><input type="checkbox" name="is_default" value="1"> افتراضي</label>
            <label class="a2-checkbox-label"><input type="checkbox" name="is_active" value="1" checked> مفعّل</label>
            <button type="submit" class="a2-btn a2-btn-primary">+ إضافة variant</button>
        </form>
    </div>

    <div class="a2-card" style="margin-top:16px;">
        <h2 class="a2-section-title">Extras (إضافات)</h2>

        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th>Group</th>
                        <th>Name</th>
                        <th>Price</th>
                        <th>Max Qty</th>
                        <th>Status</th>
                        <th style="width:120px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($row->extras as $extra)
                    @php $extraFormId = 'extra-form-' . $extra->id; @endphp
                    <tr>
                        <td><input class="a2-input" form="{{ $extraFormId }}" type="text" name="group_key" value="{{ $extra->group_key }}"></td>
                        <td><input class="a2-input" form="{{ $extraFormId }}" type="text" name="name_ar" value="{{ $extra->name_ar }}" required></td>
                        <td><input class="a2-input" form="{{ $extraFormId }}" type="number" step="0.01" name="price" value="{{ $extra->price }}" required></td>
                        <td><input class="a2-input" form="{{ $extraFormId }}" type="number" name="max_qty" value="{{ $extra->max_qty }}"></td>
                        <td><input form="{{ $extraFormId }}" type="checkbox" name="is_active" value="1" @checked($extra->is_active)></td>
                        <td>
                            <form id="{{ $extraFormId }}" method="POST" action="{{ route('admin.menu-items.extras.update', [$row, $extra]) }}" style="display:none;">
                                @csrf
                                @method('PUT')
                            </form>
                            <button type="submit" form="{{ $extraFormId }}" class="a2-btn a2-btn-ghost a2-btn-sm">حفظ</button>

                            <form method="POST" action="{{ route('admin.menu-items.extras.destroy', [$row, $extra]) }}" onsubmit="return confirm('حذف الإضافة؟');" style="display:inline;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="a2-btn a2-btn-danger a2-btn-sm">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="a2-empty-cell">لا توجد إضافات</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <form method="POST" action="{{ route('admin.menu-items.extras.store', $row) }}" class="a2-form-grid" style="margin-top:12px;">
            @csrf
            <input class="a2-input" type="text" name="group_key" placeholder="المجموعة (Sauces..)">
            <input class="a2-input" type="text" name="name_ar" placeholder="الاسم بالعربي" required>
            <input class="a2-input" type="number" step="0.01" name="price" placeholder="السعر" required>
            <input class="a2-input" type="number" name="max_qty" placeholder="أقصى كمية">
            <label class="a2-checkbox-label"><input type="checkbox" name="is_active" value="1" checked> مفعّل</label>
            <button type="submit" class="a2-btn a2-btn-primary">+ إضافة extra</button>
        </form>
    </div>
</div>
@endsection
