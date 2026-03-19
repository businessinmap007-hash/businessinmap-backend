@php
    /** @var \App\Models\Category|null $row */
    $category = $row ?? $category ?? null;
    $isEdit = isset($category) && $category?->exists;

    $rootIdInt = (int) ($rootId ?? request()->get('root_id', 0));
    $categoryParentId = (int) old('parent_id', $category->parent_id ?? ($defaultParentId ?? 0));
    $isRoot = ($categoryParentId === 0);

    $imgPath = $category->image ?? null;

    $selectedServices = collect(old(
        'platform_service_ids',
        $selectedPlatformServices ?? (
            isset($category) && method_exists($category, 'categoryPlatformServices')
                ? $category->categoryPlatformServices->pluck('platform_service_id')->all()
                : []
        )
    ))->map(fn($v) => (int) $v)->all();

    $platformServices = $platformServices ?? collect();

    $assignedOptions = collect(
        isset($category) && $category && $category->exists && $category->relationLoaded('categoryOptions')
            ? $category->categoryOptions->map(fn ($opt) => [
                'id' => $opt->id,
                'name_ar' => $opt->name_ar,
                'name_en' => $opt->name_en,
            ])->all()
            : []
    );
@endphp

<input type="hidden" name="root_id" value="{{ $rootIdInt }}">

<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">البيانات الأساسية</div>
            <div class="a2-card-sub">الاسم، الرابط المختصر، المستوى، الحالة</div>
        </div>
    </div>

    <div class="a2-form-grid">
        <div class="a2-form-group">
            <label class="a2-label">الاسم عربي <span class="a2-danger">*</span></label>
            <input class="a2-input" name="name_ar" value="{{ old('name_ar', $category->name_ar ?? '') }}">
            @error('name_ar')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">الاسم إنجليزي</label>
            <input class="a2-input" name="name_en" value="{{ old('name_en', $category->name_en ?? '') }}" dir="ltr">
            @error('name_en')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group a2-field-full">
            <label class="a2-label">Slug</label>
            <input class="a2-input" name="slug" value="{{ old('slug', $category->slug ?? '') }}" dir="ltr" placeholder="example-category">
            <div class="a2-section-subtitle" style="margin-top:8px;margin-bottom:0;">
                اتركه فارغًا ليتم توليده تلقائيًا.
            </div>
            @error('slug')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">المستوى / الأب</label>
            <select class="a2-select" name="parent_id">
                <option value="0" @selected((string) $categoryParentId === '0')>
                    Root (قسم رئيسي)
                </option>

                @foreach(($parents ?? []) as $p)
                    <option value="{{ $p->id }}" @selected((string) $categoryParentId === (string) $p->id)>
                        Child of: #{{ $p->id }} - {{ $p->name_ar ?: ($p->name_en ?: '—') }}
                    </option>
                @endforeach
            </select>
            @error('parent_id')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">الحالة</label>
            <select class="a2-select" name="is_active">
                <option value="1" @selected((string) old('is_active', $category->is_active ?? 1) === '1')>Active</option>
                <option value="0" @selected((string) old('is_active', $category->is_active ?? 1) === '0')>Inactive</option>
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
            <div class="a2-card-title">الأسعار والترتيب</div>
            <div class="a2-card-sub">بيانات الاشتراك وترتيب الظهور</div>
        </div>
    </div>

    <div class="a2-form-grid-3">
        <div class="a2-form-group">
            <label class="a2-label">السعر الشهري</label>
            <input class="a2-input" name="per_month" value="{{ old('per_month', $category->per_month ?? '') }}" inputmode="decimal">
            @error('per_month')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">السعر السنوي</label>
            <input class="a2-input" name="per_year" value="{{ old('per_year', $category->per_year ?? '') }}" inputmode="decimal">
            @error('per_year')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">الترتيب (reorder)</label>
            <input class="a2-input" name="reorder" value="{{ old('reorder', $category->reorder ?? '') }}" inputmode="numeric">
            @error('reorder')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">صورة القسم</div>
            <div class="a2-card-sub">رفع صورة مع معاينة مباشرة</div>
        </div>
    </div>

    <div class="a2-form-grid">
        <div class="a2-form-group">
            <label class="a2-label">صورة القسم</label>
            <input class="a2-input" type="file" name="image" accept="image/*" id="category-image-input">
            <div class="a2-section-subtitle" style="margin-top:8px;margin-bottom:0;">
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
                    <span class="a2-section-subtitle" style="margin:0;">اختر صورة</span>
                @endif
            </div>
        </div>
    </div>

    @if($isRoot)
        <div class="a2-alert a2-alert-warning" style="margin-top:14px;">
            هذا قسم رئيسي، وغالبًا تُدار عليه الصورة العامة والأسعار الأساسية الخاصة بالاشتراك.
        </div>
    @endif
</div>

<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">خدمات التصنيف</div>
            <div class="a2-card-sub">يمكن اختيار خدمة أو أكثر لنفس التصنيف</div>
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
                    <small style="display:block;color:var(--a2-text-soft);margin-top:4px;" dir="ltr">
                        {{ $service->key }}
                    </small>
                </span>
            </label>
        @empty
            <div class="a2-alert a2-alert-warning">
                لا توجد خدمات مضافة في Platform Services حتى الآن.
            </div>
        @endforelse
    </div>

    @error('platform_service_ids')
        <div class="a2-error" style="margin-top:10px;">{{ $message }}</div>
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
                <div class="a2-card-title">خيارات القسم الفرعي</div>
                <div class="a2-card-sub">عرض الخيارات المرتبطة بهذا القسم مع الانتقال لصفحة الإدارة</div>
            </div>

            <div class="a2-page-actions">
                <a href="{{ route('admin.categories.options.edit', $category->id) }}"
                   class="a2-btn a2-btn-primary">
                    إدارة Options
                </a>
            </div>
        </div>

        @if($assignedOptions->count())
            <div class="a2-option-chip-grid">
                @foreach($assignedOptions as $opt)
                    <div class="a2-option-chip-card">
                        <div class="a2-option-chip-title">
                            {{ $opt['name_ar'] ?? ($opt['name_en'] ?? ('#' . ($opt['id'] ?? ''))) }}
                        </div>
                        <div class="a2-option-chip-sub" dir="ltr">
                            #{{ $opt['id'] ?? '' }}
                            @if(!empty($opt['name_en']))
                                — {{ $opt['name_en'] }}
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="a2-alert a2-alert-warning" style="margin-top:12px;">
                لا توجد Options مرتبطة بهذا القسم حتى الآن.
            </div>
        @endif
    </div>
@endif

<div class="a2-page-actions" style="justify-content:flex-end;margin-top:16px;">
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
                imageBox.innerHTML = '<span class="a2-section-subtitle" style="margin:0;">اختر صورة</span>';
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