@php
    /** @var \App\Models\Category|null $row */
    $category = $row ?? $category ?? null;
    $isEdit = isset($category) && $category?->exists;

    $rootIdInt = (int) ($rootId ?? request()->get('root_id', 0));
    $imgPath = $category->image ?? null;

    $selectedServices = collect(old(
        'platform_service_ids',
        $selectedPlatformServices ?? (
            isset($category) && method_exists($category, 'categoryPlatformServices')
                ? $category->categoryPlatformServices->pluck('platform_service_id')->all()
                : []
        )
    ))->map(fn ($v) => (int) $v)->all();

    $platformServices = $platformServices ?? collect();
@endphp

<input type="hidden" name="root_id" value="{{ $rootIdInt }}">
<input type="hidden" name="parent_id" value="0">

<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div>
            <div class="a2-section-title a2-mb-0">البيانات الأساسية</div>
        </div>
    </div>

    <div class="a2-alert a2-alert-warning">
        لا يمكن من هذه الشاشة إنشاء أو تعديل قسم فرعي. الأقسام الفرعية أصبحت موحّدة في جداول مستقلة ويتم إدارتها من شاشة منفصلة.
    </div>

    <div class="a2-form-grid">
        <div class="a2-form-group">
            <label class="a2-label">الاسم عربي <span style="color:var(--a2-danger)">*</span></label>
            <input class="a2-input"
                   name="name_ar"
                   value="{{ old('name_ar', $category->name_ar ?? '') }}"
                   placeholder="اسم القسم الرئيسي">
            @error('name_ar')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">الاسم إنجليزي</label>
            <input class="a2-input"
                   name="name_en"
                   value="{{ old('name_en', $category->name_en ?? '') }}"
                   dir="ltr"
                   placeholder="Category name in English">
            @error('name_en')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group a2-field-full">
            <label class="a2-label">Slug</label>
            <input class="a2-input"
                   name="slug"
                   value="{{ old('slug', $category->slug ?? '') }}"
                   dir="ltr"
                   placeholder="example-category">
            <div class="a2-section-subtitle a2-mb-0 a2-mt-8">
                اتركه فارغًا ليتم توليده تلقائيًا.
            </div>
            @error('slug')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">نوع السجل</label>
            <input class="a2-input" value="قسم رئيسي / Root Category" disabled>
        </div>

        <div class="a2-form-group">
            <label class="a2-label">الحالة</label>
            <select class="a2-select" name="is_active">
                <option value="1" @selected((string) old('is_active', $category->is_active ?? 1) === '1')>نشط</option>
                <option value="0" @selected((string) old('is_active', $category->is_active ?? 1) === '0')>غير نشط</option>
            </select>
            @error('is_active')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div>
            <div class="a2-section-title a2-mb-0">الأسعار والترتيب</div>
        </div>
    </div>

    <div class="a2-form-grid-3">
        <div class="a2-form-group">
            <label class="a2-label">السعر الشهري</label>
            <input class="a2-input"
                   name="per_month"
                   value="{{ old('per_month', $category->per_month ?? '') }}"
                   inputmode="decimal"
                   placeholder="0.00">
            @error('per_month')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">السعر السنوي</label>
            <input class="a2-input"
                   name="per_year"
                   value="{{ old('per_year', $category->per_year ?? '') }}"
                   inputmode="decimal"
                   placeholder="0.00">
            @error('per_year')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">الترتيب</label>
            <input class="a2-input"
                   name="reorder"
                   value="{{ old('reorder', $category->reorder ?? '') }}"
                   inputmode="numeric"
                   placeholder="0">
            @error('reorder')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div>
            <div class="a2-section-title a2-mb-0">صورة القسم</div>
        </div>
    </div>

    <div class="a2-form-grid">
        <div class="a2-form-group">
            <label class="a2-label">صورة القسم</label>
            <input class="a2-input" type="file" name="image" accept="image/*" id="category-image-input">
            <div class="a2-section-subtitle a2-mb-0 a2-mt-8">
                JPG / PNG / WEBP بحد أقصى 2MB
            </div>
            @error('image')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">المعاينة</label>
            <div class="a2-card a2-card--soft" id="imgPreviewBox" style="min-height:180px;display:flex;align-items:center;justify-content:center;">
                @if($imgPath)
                    <x-admin-v2.image :path="$imgPath" size="140" radius="16px" />
                @else
                    <span class="a2-section-subtitle a2-mb-0">اختر صورة</span>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div>
            <div class="a2-section-title a2-mb-0">خدمات التصنيف</div>
        </div>
    </div>

    <div class="a2-check-grid">
        @forelse($platformServices as $service)
            <label class="a2-check-card">
                <input type="checkbox"
                       name="platform_service_ids[]"
                       value="{{ $service->id }}"
                       class="js-service-checkbox"
                       data-service-key="{{ $service->key }}"
                       @checked(in_array((int) $service->id, $selectedServices, true))>
                <span>
                    <strong>{{ $service->name_ar ?: ($service->name_en ?: $service->key) }}</strong>
                    <small class="a2-muted" dir="ltr">{{ $service->key }}</small>
                </span>
            </label>
        @empty
            <div class="a2-alert a2-alert-warning">
                لا توجد خدمات مضافة في Platform Services حتى الآن.
            </div>
        @endforelse
    </div>

    @error('platform_service_ids')
        <div class="a2-error a2-mt-8">{{ $message }}</div>
    @enderror
</div>

@if(file_exists(resource_path('views/admin-v2/categories/services/booking.blade.php')))
    @include('admin-v2.categories.services.booking', [
        'platformServices' => $platformServices,
        'selectedPlatformServices' => $selectedServices,
        'bookingConfig' => $bookingConfig ?? [],
    ])
@endif

@if(file_exists(resource_path('views/admin-v2/categories/services/menu.blade.php')))
    @include('admin-v2.categories.services.menu', [
        'platformServices' => $platformServices,
        'selectedPlatformServices' => $selectedServices,
        'menuConfig' => $menuConfig ?? [],
    ])
@endif

@if(file_exists(resource_path('views/admin-v2/categories/services/delivery.blade.php')))
    @include('admin-v2.categories.services.delivery', [
        'platformServices' => $platformServices,
        'selectedPlatformServices' => $selectedServices,
        'deliveryConfig' => $deliveryConfig ?? [],
    ])
@endif

@if($category && $category->exists)
    <div class="a2-card a2-card--section">
        <div class="a2-card-head">
            <div>
                <div class="a2-section-title a2-mb-0">الأقسام الفرعية المرتبطة</div>
            </div>

            <div class="a2-page-actions">
                <a href="{{ route('admin.category-children.index', ['parent_id' => $category->id]) }}"
                   class="a2-btn a2-btn-ghost a2-btn-sm">
                    إدارة الأقسام الفرعية
                </a>
            </div>
        </div>

        @php
            $linkedChildren = $category->relationLoaded('children') ? $category->children : collect();
        @endphp

        @if($linkedChildren->count())
            <div class="a2-option-chip-grid">
                @foreach($linkedChildren as $child)
                    <div class="a2-option-chip-card">
                        <div class="a2-option-chip-title">
                            {{ $child->name_ar ?: ($child->name_en ?: ('#' . $child->id)) }}
                        </div>
                        <div class="a2-option-chip-sub" dir="ltr">
                            #{{ $child->id }}
                            @if(!empty($child->name_en))
                                — {{ $child->name_en }}
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="a2-alert a2-alert-warning a2-mt-12">
                لا توجد أقسام فرعية مرتبطة بهذا القسم الرئيسي حتى الآن.
            </div>
        @endif
    </div>
@endif

<div class="a2-page-actions" style="justify-content:flex-end;">
    @if(!empty($backUrl ?? null))
        <a href="{{ $backUrl }}" class="a2-btn a2-btn-ghost">رجوع</a>
    @endif

    <button type="submit" class="a2-btn a2-btn-primary">
        {{ $isEdit ? 'تحديث' : 'حفظ' }}
    </button>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const imageInput = document.getElementById('category-image-input');
    const imageBox = document.getElementById('imgPreviewBox');
    const serviceCheckboxes = document.querySelectorAll('.js-service-checkbox');
    const servicePanels = document.querySelectorAll('.js-service-panel');

    function refreshServicePanels() {
        const activeKeys = [];

        serviceCheckboxes.forEach(function (checkbox) {
            if (checkbox.checked) {
                activeKeys.push(checkbox.dataset.serviceKey);
            }
        });

        servicePanels.forEach(function (panel) {
            const panelKey = panel.getAttribute('data-service-panel');
            panel.style.display = activeKeys.includes(panelKey) ? '' : 'none';
        });
    }

    if (serviceCheckboxes.length) {
        serviceCheckboxes.forEach(function (checkbox) {
            checkbox.addEventListener('change', refreshServicePanels);
        });

        refreshServicePanels();
    }

    if (imageInput && imageBox) {
        imageInput.addEventListener('change', function () {
            imageBox.innerHTML = '';

            const file = imageInput.files && imageInput.files[0];
            if (!file) {
                imageBox.innerHTML = '<span class="a2-section-subtitle a2-mb-0">اختر صورة</span>';
                return;
            }

            const img = document.createElement('img');
            img.alt = 'preview';
            img.style.width = '100%';
            img.style.maxHeight = '240px';
            img.style.objectFit = 'cover';
            img.style.borderRadius = '14px';
            img.src = URL.createObjectURL(file);

            imageBox.appendChild(img);
        });
    }
});
</script>
@endpush