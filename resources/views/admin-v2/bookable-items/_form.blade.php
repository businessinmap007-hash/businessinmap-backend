@php
    $isEdit = isset($row) && $row->exists;
    $defaultBusinessId = old('business_id', $row->business_id ?? '');
    $defaultServiceId = old('service_id', $row->service_id ?? '');
    $defaultType = old('item_type', $row->item_type ?? '');
    $typeOptions = $allowedItemTypes ?? [];
@endphp

<div class="a2-card a2-card--soft a2-mb-16">
    <div class="a2-section-title">تنظيم عناصر الحجز الفعلية</div>
    <div class="a2-section-subtitle">
        Business Service Price يحدد سعر/سياسة الخدمة العامة عند البزنس حسب نوع العنصر.
        أما Bookable Item فهو العنصر الحقيقي الذي يظهر للعميل في الحجز مثل غرفة 101 أو شقة A1 أو طاولة 5.
    </div>
</div>

<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">البزنس والخدمة</div>
            <div class="a2-card-sub">اختر البزنس ثم الخدمة، وبعدها أضف عناصر الحجز الفعلية.</div>
        </div>
    </div>

    <div class="a2-form-grid-3">
        <div class="a2-form-group">
            <label class="a2-label">البزنس</label>
            <select name="business_id" class="a2-select" required>
                <option value="">اختر البزنس</option>
                @foreach($businesses as $b)
                    <option value="{{ $b->id }}" @selected((string) $defaultBusinessId === (string) $b->id)>
                        {{ $b->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="a2-form-group">
            <label class="a2-label">الخدمة</label>
            <select name="service_id" class="a2-select" required>
                <option value="">اختر الخدمة</option>
                @foreach($services as $s)
                    <option value="{{ $s->id }}" @selected((string) $defaultServiceId === (string) $s->id)>
                        {{ $s->name_ar ?? $s->name_en ?? $s->key }}
                    </option>
                @endforeach
            </select>
        </div>

        @if($isEdit)
            <div class="a2-form-group">
                <label class="a2-label">نوع العنصر</label>
                @if(!empty($typeOptions))
                    <select name="item_type" class="a2-select" required>
                        <option value="">اختر النوع</option>
                        @foreach($typeOptions as $type)
                            <option value="{{ $type }}" @selected((string) $defaultType === (string) $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                @else
                    <input type="text" name="item_type" class="a2-input" value="{{ $defaultType }}" required>
                @endif
            </div>
        @endif
    </div>
</div>

@if($isEdit)
    <div class="a2-form-grid">
        <div class="a2-card">
            <div class="a2-card-head"><h3>بيانات العنصر</h3></div>
            <div class="a2-card-body">
                <div class="a2-form-group">
                    <label>العنوان</label>
                    <input type="text" name="title" class="a2-input" value="{{ old('title', $row->title ?? '') }}" required>
                </div>
                <div class="a2-form-group">
                    <label>الكود / رقم الغرفة</label>
                    <input type="text" name="code" class="a2-input" value="{{ old('code', $row->code ?? '') }}">
                </div>
                <div class="a2-form-group">
                    <label>السعة</label>
                    <input type="number" name="capacity" class="a2-input" value="{{ old('capacity', $row->capacity ?? '') }}">
                </div>
                <div class="a2-form-group">
                    <label>الكمية</label>
                    <input type="number" name="quantity" class="a2-input" value="{{ old('quantity', $row->quantity ?? 1) }}">
                </div>
            </div>
        </div>
        <div class="a2-card">
            <div class="a2-card-head"><h3>السعر والحالة</h3></div>
            <div class="a2-card-body">
                <div class="a2-form-group">
                    <label>السعر</label>
                    <input type="number" step="0.01" name="price" class="a2-input" value="{{ old('price', $row->price ?? 0) }}" required>
                </div>
                <label class="a2-check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $row->is_active ?? 1))> <span>مفعل</span></label>
            </div>
        </div>
    </div>
@else
    <div class="a2-card a2-card--section">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">عناصر الحجز المتاحة للعميل</div>
                <div class="a2-card-sub">أضف كل عنصر فعلي كسطر مستقل: شقة، غرفة، رقم غرفة، طاولة، قاعة.</div>
            </div>
        </div>

        <div class="a2-table-wrap">
            <table class="a2-table" id="bookableItemsTable">
                <thead>
                    <tr>
                        <th>نوع العنصر</th>
                        <th>العنوان</th>
                        <th>الكود / رقم الغرفة</th>
                        <th>السعة / الغرف</th>
                        <th>الكمية</th>
                        <th>السعر</th>
                        <th>مفعل</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @for($i = 0; $i < 6; $i++)
                        <tr>
                            <td>
                                @if(!empty($typeOptions))
                                    <select name="items[{{ $i }}][item_type]" class="a2-select">
                                        <option value="">اختر</option>
                                        @foreach($typeOptions as $type)
                                            <option value="{{ $type }}">{{ $type }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input name="items[{{ $i }}][item_type]" class="a2-input" placeholder="apartment / room / table">
                                @endif
                            </td>
                            <td><input name="items[{{ $i }}][title]" class="a2-input" placeholder="مثال: شقة 1 أو غرفة 101"></td>
                            <td><input name="items[{{ $i }}][code]" class="a2-input" placeholder="101 / A1"></td>
                            <td><input name="items[{{ $i }}][capacity]" class="a2-input" type="number" min="1" placeholder="مثال: 2"></td>
                            <td><input name="items[{{ $i }}][quantity]" class="a2-input" type="number" min="1" value="1"></td>
                            <td><input name="items[{{ $i }}][price]" class="a2-input" type="number" step="0.01" min="0" placeholder="0.00"></td>
                            <td><input type="checkbox" name="items[{{ $i }}][is_active]" value="1" checked></td>
                            <td><button type="button" class="a2-btn a2-btn-ghost js-clear-row">مسح</button></td>
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>

        <div class="a2-alert a2-alert-info a2-mt-16">
            يمكن ترك الأسطر الفارغة. سيتم إنشاء الأسطر التي تحتوي على عنوان أو كود أو سعر فقط.
        </div>
    </div>
@endif

<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">الديبوزت والخصم على المجموعة</div>
            <div class="a2-card-sub">تطبق هذه الإعدادات على العناصر التي سيتم إنشاؤها من هذا النموذج.</div>
        </div>
    </div>

    <div class="a2-form-grid-3">
        <label class="a2-check-card">
            <input type="checkbox" name="deposit_enabled" value="1" id="deposit_enabled" @checked(old('deposit_enabled', $row->deposit_enabled ?? 0))>
            <span>تفعيل الديبوزت</span>
        </label>

        <div class="a2-form-group">
            <label class="a2-label">نسبة الديبوزت %</label>
            <input type="number" name="deposit_percent" id="deposit_percent" class="a2-input" value="{{ old('deposit_percent', $row->deposit_percent ?? 0) }}" min="0" max="100">
        </div>

        <div class="a2-form-group">
            <label class="a2-label">خصم الخدمة</label>
            <div class="a2-section-subtitle">الخصم الأساسي للخدمة يدار من Business Service Prices وليس من العنصر الفردي.</div>
            <a href="{{ route('admin.business_service_prices.index') }}" class="a2-btn a2-btn-ghost">إدارة أسعار وخصومات الخدمة</a>
        </div>
    </div>
</div>

<div class="a2-card a2-card--section">
    <div class="a2-card-head"><div class="a2-card-title">Meta JSON</div></div>
    <textarea name="meta" class="a2-textarea" rows="4" placeholder='{"floor":"1", "view":"sea"}'>{{ old('meta', isset($row->meta) ? json_encode($row->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '') }}</textarea>
</div>

<div class="a2-actions">
    <button class="a2-btn a2-btn-primary">{{ $isEdit ? 'تحديث' : 'إنشاء العناصر' }}</button>
    <a href="{{ route('admin.bookable-items.index') }}" class="a2-btn">رجوع</a>
</div>

@push('scripts')
<script>
document.addEventListener('click', function (event) {
    const btn = event.target.closest('.js-clear-row');
    if (!btn) return;
    const row = btn.closest('tr');
    row.querySelectorAll('input, select').forEach(function (input) {
        if (input.type === 'checkbox') input.checked = false;
        else input.value = '';
    });
});

document.addEventListener('DOMContentLoaded', function () {
    const depositCheckbox = document.getElementById('deposit_enabled');
    const depositInput = document.getElementById('deposit_percent');
    function toggleDeposit() {
        if (!depositCheckbox || !depositInput) return;
        depositInput.disabled = !depositCheckbox.checked;
        if (!depositCheckbox.checked) depositInput.value = 0;
    }
    depositCheckbox?.addEventListener('change', toggleDeposit);
    toggleDeposit();
});
</script>
@endpush
