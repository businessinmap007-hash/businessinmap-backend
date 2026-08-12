@php
    $id = (int) ($user->id ?? 0);

    $logoPath  = $user->logo ?? null;
    $imagePath = $user->image ?? null;
    $coverPath = $user->cover ?? null;

    $currentType = old('type', $user->type ?? 'client');
    $currentCategoryId = (int) old('category_id', $user->category_id ?? 0);
    $currentChildId = (int) old('category_child_id', $user->category_child_id ?? 0);

    $oldOptions = old('options');
    $selectedIds = is_array($oldOptions)
        ? collect($oldOptions)->map(fn ($v) => (int) $v)->filter()->values()->all()
        : collect($selectedOptionIds ?? [])->map(fn ($v) => (int) $v)->filter()->values()->all();

    $oldServices = old('service_ids');
    $selectedServiceIdsNow = is_array($oldServices)
        ? collect($oldServices)->map(fn ($v) => (int) $v)->filter()->values()->all()
        : collect($selectedServiceIds ?? [])->map(fn ($v) => (int) $v)->filter()->values()->all();

    $childCatalogJson = json_encode($childCatalog ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // The child this user already has, so opening the form costs no request.
    // Built here rather than inline: @json() cannot parse a multi-line array
    // literal — Blade ends the directive at the first ')'.
    $catalogSeed = (int) ($user->category_child_id ?? 0) > 0
        ? [(int) $user->category_child_id => [
            'groups' => $groups ?? [],
            'ungrouped' => $ungroupedOptions ?? [],
            'services' => $services ?? [],
        ]]
        : (object) [];
@endphp

<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div>
            <div class="a2-section-title a2-mb-0">{{ __('البيانات الأساسية') }}</div>
        </div>
    </div>

    <div class="a2-form-grid-3">
        <div class="a2-form-group">
            <label class="a2-label">{{ __('الاسم') }} <span class="a2-muted">({{ __('عربي') }})</span></label>
            <input class="a2-input" name="name" value="{{ old('name', $user->name) }}">
            @error('name')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        {{-- «الاسم فى حسابات البزنس يكون خانتين عربى وانجليزى / حسابات العملاء
             خانة واحدة وتقبل الاثنين».

             A shop is searched for; a customer is not. 605 of 1,704 businesses
             carried a Latin-only name and no Arabic search could reach them. --}}
        <div class="a2-form-group">
            <label class="a2-label">
                {{ __('الاسم بالإنجليزية') }}
                <span class="a2-muted">({{ __('للأنشطة التجارية') }})</span>
            </label>
            <input class="a2-input" name="name_en" dir="ltr" value="{{ old('name_en', $user->name_en) }}">
            <div class="a2-hint">{{ __('يجعل النشاط يظهر لمن يبحث بالإنجليزية — وخانة العميل الواحدة تقبل اللغتين.') }}</div>
            @error('name_en')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">Phone</label>
            <input class="a2-input" name="phone" value="{{ old('phone', $user->phone) }}">
            @error('phone')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">Email</label>
            <input class="a2-input" name="email" value="{{ old('email', $user->email) }}" dir="ltr">
            @error('email')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">Type</label>
            <select class="a2-select" name="type" id="user_type">
                <option value="client" @selected($currentType === 'client')>client</option>
                <option value="business" @selected($currentType === 'business')>business</option>
                <option value="admin" @selected($currentType === 'admin')>admin</option>
            </select>
            @error('type')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">Code</label>
            <input class="a2-input" name="code" value="{{ old('code', $user->code) }}">
            @error('code')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">Action Code</label>
            <input class="a2-input" name="action_code" value="{{ old('action_code', $user->action_code) }}">
            @error('action_code')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">Latitude</label>
            <input class="a2-input" name="latitude" value="{{ old('latitude', $user->latitude) }}">
            @error('latitude')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">Longitude</label>
            <input class="a2-input" name="longitude" value="{{ old('longitude', $user->longitude) }}">
            @error('longitude')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group a2-field-full">
            <label class="a2-label">About</label>
            <textarea class="a2-textarea" name="about" rows="4">{{ old('about', $user->about) }}</textarea>
            @error('about')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div id="business_fields_wrap" style="display:none;">
    <div class="a2-card a2-card--section">
        <div class="a2-card-head">
            <div>
                <div class="a2-section-title a2-mb-0">{{ __('تصنيف الحساب التجاري') }}</div>
            </div>
        </div>

        <div class="a2-form-grid">
            <div class="a2-form-group">
                <label class="a2-label">Category</label>
                <select class="a2-select" name="category_id" id="business_category_id">
                    <option value="">{{ __('-- اختر التصنيف الرئيسي --') }}</option>
                    @foreach(($categories ?? []) as $category)
                        <option value="{{ $category->id }}" @selected($currentCategoryId === (int) $category->id)>
                            {{ $category->name_ar ?: ($category->name_en ?: ('#' . $category->id)) }}
                        </option>
                    @endforeach
                </select>
                @error('category_id')
                    <div class="a2-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="a2-form-group">
                <label class="a2-label">Category Child</label>
                <select class="a2-select" name="category_child_id" id="business_category_child_id" data-no-ts="1">
                    <option value="">{{ __('-- اختر القسم الفرعي --') }}</option>
                    @foreach(($children ?? []) as $child)
                        <option value="{{ $child->id }}" @selected($currentChildId === (int) $child->id)>
                            {{ $child->name_ar ?: ($child->name_en ?: ('#' . $child->id)) }}
                        </option>
                    @endforeach
                </select>
                @error('category_child_id')
                    <div class="a2-error">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div style="margin-top:14px;">
            <label class="a2-label">Options</label>
            <div id="business_options_wrap">
                @if(collect($groups ?? [])->count() || collect($ungroupedOptions ?? [])->count())
                    <div style="display:grid;gap:14px;">
                        @foreach(($groups ?? []) as $group)
                            <div class="a2-card a2-card--soft a2-card--tight">
                                <div style="font-weight:900;margin-bottom:10px;">
                                    {{ $group->name_ar ?: ($group->name_en ?: ('#' . $group->id)) }}
                                </div>

                                <div class="a2-form-grid-3">
                                    @foreach(($group->options ?? []) as $opt)
                                        <label class="a2-check">
                                            <input
                                                type="checkbox"
                                                name="options[]"
                                                value="{{ $opt->id }}"
                                                @checked(in_array((int) $opt->id, $selectedIds, true))
                                            >
                                            <span>{{ $opt->name_ar ?: ($opt->name_en ?: ('#' . $opt->id)) }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach

                        @if(collect($ungroupedOptions ?? [])->count())
                            <div class="a2-card a2-card--soft a2-card--tight">
                                <div style="font-weight:900;margin-bottom:10px;">{{ __('خيارات بدون مجموعة') }}</div>

                                <div class="a2-form-grid-3">
                                    @foreach(($ungroupedOptions ?? []) as $opt)
                                        <label class="a2-check">
                                            <input
                                                type="checkbox"
                                                name="options[]"
                                                value="{{ $opt->id }}"
                                                @checked(in_array((int) $opt->id, $selectedIds, true))
                                            >
                                            <span>{{ $opt->name_ar ?: ($opt->name_en ?: ('#' . $opt->id)) }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="a2-muted">{{ __('اختر القسم الفرعي لعرض الخيارات المتاحة.') }}</div>
                @endif
            </div>
            @error('options')
                <div class="a2-error">{{ $message }}</div>
            @enderror
            @error('options.*')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div style="margin-top:14px;">
            <label class="a2-label">Services</label>
            <div id="business_services_wrap">
                @if(collect($services ?? [])->count())
                    <div class="a2-card a2-card--soft a2-card--tight">
                        <div class="a2-form-grid-3">
                            @foreach(($services ?? []) as $srv)
                                <label class="a2-check">
                                    <input
                                        type="checkbox"
                                        name="service_ids[]"
                                        value="{{ $srv->id }}"
                                        @checked(in_array((int) $srv->id, $selectedServiceIdsNow, true))
                                    >
                                    <span>{{ $srv->name_ar ?: ($srv->name_en ?: ('#' . $srv->id)) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="a2-muted">{{ __('اختر القسم الفرعي لعرض الخدمات المتاحة.') }}</div>
                @endif
            </div>
            @error('service_ids')
                <div class="a2-error">{{ $message }}</div>
            @enderror
            @error('service_ids.*')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div>
            <div class="a2-section-title a2-mb-0">{{ __('صور الحساب') }}</div>
        </div>
    </div>

    <div class="a2-form-grid">
        <div class="a2-form-group">
            <label class="a2-label">Logo</label>
            <input class="a2-input" type="file" name="logo" accept="image/*" id="user-logo-input">
            @error('logo')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">{{ __('المعاينة') }}</label>
            <div class="a2-card a2-card--soft" id="logoPreviewBox" style="min-height:180px;display:flex;align-items:center;justify-content:center;">
                @if($logoPath)
                    <x-admin-v2.image :path="$logoPath" size="140" radius="16px" />
                @else
                    <span class="a2-section-subtitle a2-mb-0">{{ __('اختر صورة') }}</span>
                @endif
            </div>
        </div>
    </div>

    <div class="a2-divider"></div>

    <div class="a2-form-grid-3">
        <div class="a2-form-group">
            <x-admin-v2.image-upload name="image" label="Image" :path="$user->image" />
            @error('image')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <x-admin-v2.image-upload name="logo" label="Logo" :path="$user->logo" />
            @error('logo')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <x-admin-v2.image-upload name="cover" label="Cover" :path="$user->cover" />
            @error('cover')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div>
            <div class="a2-section-title a2-mb-0">{{ __('معلومات النظام') }}</div>
        </div>
    </div>

    <div class="a2-form-grid-3">
        <div class="a2-form-group">
            <label class="a2-label">Activated At</label>
            <input class="a2-input" value="{{ $user->activated_at }}" readonly>
        </div>

        <div class="a2-form-group">
            <label class="a2-label">Paid At</label>
            <input class="a2-input" value="{{ $user->paid_at }}" readonly>
        </div>

        <div class="a2-form-group">
            <label class="a2-label">Balance</label>
            <input class="a2-input" value="{{ $user->balance }}" readonly>
        </div>

        <div class="a2-form-group">
            <label class="a2-label">Pin Attempts</label>
            <input class="a2-input" value="{{ $user->pin_attempts }}" readonly>
        </div>

        <div class="a2-form-group">
            <label class="a2-label">Pin Locked Until</label>
            <input class="a2-input" value="{{ $user->pin_locked_until }}" readonly>
        </div>

        <div class="a2-form-group">
            <label class="a2-label">Deleted At</label>
            <input class="a2-input" value="{{ $user->deleted_at }}" readonly>
        </div>
    </div>
</div>

<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div>
            <div class="a2-section-title a2-mb-0">{{ __('تغيير كلمة المرور') }}</div>
        </div>
    </div>

    <div class="a2-form-grid">
        <div class="a2-form-group">
            <label class="a2-label">Password</label>
            <input type="password" class="a2-input" name="password">
            @error('password')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">Confirm Password</label>
            <input type="password" class="a2-input" name="password_confirmation">
        </div>
    </div>
</div>

<div class="a2-page-actions" style="justify-content:flex-end;">
    <button class="a2-btn a2-btn-primary" type="submit">{{ $submitLabel ?? 'حفظ' }}</button>
    <a class="a2-btn a2-btn-ghost" href="{{ route('admin.users.show', $id) }}">{{ __('إلغاء') }}</a>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const typeEl = document.getElementById('user_type');
    const categoryEl = document.getElementById('business_category_id');
    const childEl = document.getElementById('business_category_child_id');
    const businessWrap = document.getElementById('business_fields_wrap');
    const optionsWrap = document.getElementById('business_options_wrap');
    const servicesWrap = document.getElementById('business_services_wrap');

    const childCatalog = {!! $childCatalogJson ?: '{}' !!};

    /*
     * The option map for ALL 337 children used to ship with this form — four
     * queries each to build, 1,399 queries and 3.1 seconds to open ONE user,
     * and 668KB on the wire. The form shows one child at a time.
     *
     * It is fetched for the child actually chosen, and seeded below with the
     * one the user already has, so opening the page asks for nothing and only
     * CHANGING the specialty costs a request.
     *
     * route(.., false): an absolute URL from APP_URL points at a host the panel
     * may not be served from, and the fetch dies cross-origin in silence.
     */
    const CATALOG_URL = @json(route('admin.users.catalog', [], false));

    const catalog = @json($catalogSeed);

    let selectedOptions = @json(array_values($selectedIds));
    let selectedServices = @json(array_values($selectedServiceIdsNow));

    function optionLabel(item) {
        return item.name_ar || item.name_en || ('#' + item.id);
    }

    function serviceLabel(item) {
        return item.name_ar || item.name_en || ('#' + item.id);
    }

    function renderOptions(childId) {
        const payload = catalog[String(childId || '')] || {groups: [], ungrouped: []};
        const groups = payload.groups || [];
        const ungrouped = payload.ungrouped || [];

        if (!groups.length && !ungrouped.length) {
            optionsWrap.innerHTML = '<div class="a2-muted">لا توجد خيارات متاحة لهذا القسم الفرعي.</div>';
            return;
        }

        let html = '<div style="display:grid;gap:14px;">';

        groups.forEach(group => {
            html += '<div class="a2-card a2-card--soft a2-card--tight">';
            html += '<div style="font-weight:900;margin-bottom:10px;">' + (group.name_ar || group.name_en || ('#' + group.id)) + '</div>';
            html += '<div class="a2-form-grid-3">';

            (group.options || []).forEach(opt => {
                const checked = selectedOptions.includes(Number(opt.id)) ? 'checked' : '';
                html += `
                    <label class="a2-check">
                        <input type="checkbox" name="options[]" value="${opt.id}" ${checked}>
                        <span>${optionLabel(opt)}</span>
                    </label>
                `;
            });

            html += '</div></div>';
        });

        if (ungrouped.length) {
            html += '<div class="a2-card a2-card--soft a2-card--tight">';
            html += '<div style="font-weight:900;margin-bottom:10px;">خيارات بدون مجموعة</div>';
            html += '<div class="a2-form-grid-3">';

            ungrouped.forEach(opt => {
                const checked = selectedOptions.includes(Number(opt.id)) ? 'checked' : '';
                html += `
                    <label class="a2-check">
                        <input type="checkbox" name="options[]" value="${opt.id}" ${checked}>
                        <span>${optionLabel(opt)}</span>
                    </label>
                `;
            });

            html += '</div></div>';
        }

        html += '</div>';
        optionsWrap.innerHTML = html;
    }

    function renderServices(childId) {
        const services = (catalog[String(childId || '')] || {}).services || [];

        if (!services.length) {
            servicesWrap.innerHTML = '<div class="a2-muted">لا توجد خدمات متاحة لهذا القسم الفرعي.</div>';
            return;
        }

        let html = '<div class="a2-card a2-card--soft a2-card--tight">';
        html += '<div class="a2-form-grid-3">';

        services.forEach(service => {
            const checked = selectedServices.includes(Number(service.id)) ? 'checked' : '';
            html += `
                <label class="a2-check">
                    <input type="checkbox" name="service_ids[]" value="${service.id}" ${checked}>
                    <span>${serviceLabel(service)}</span>
                </label>
            `;
        });

        html += '</div></div>';
        servicesWrap.innerHTML = html;
    }

    function renderChildren(categoryId, keepCurrent = false) {
        const children = childCatalog[String(categoryId || '')] || [];
        const currentValue = keepCurrent ? String(childEl.value || '') : '';

        childEl.innerHTML = '<option value="">-- اختر القسم الفرعي --</option>';

        children.forEach(child => {
            const selected = String(child.id) === currentValue ? 'selected' : '';
            childEl.insertAdjacentHTML(
                'beforeend',
                `<option value="${child.id}" ${selected}>${child.name_ar || child.name_en || ('#' + child.id)}</option>`
            );
        });

        if (!keepCurrent) {
            childEl.value = '';
            selectedOptions = [];
            selectedServices = [];
            showChild('');
        } else {
            showChild(childEl.value);
        }
    }

    /**
     * Draw the two panels for a child, fetching its rows first if this is the
     * first time it has been chosen. Answers can arrive out of order, so only
     * the newest may paint.
     */
    let catalogSeq = 0;

    function showChild(childId) {
        const key = String(childId || '');

        if (!key || catalog[key]) {
            renderOptions(key);
            renderServices(key);

            return;
        }

        const mine = ++catalogSeq;
        optionsWrap.innerHTML = '<div class="a2-muted">…</div>';
        servicesWrap.innerHTML = '<div class="a2-muted">…</div>';

        fetch(CATALOG_URL + '?child_id=' + encodeURIComponent(key), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (response) { return response.json(); })
            .then(function (body) {
                catalog[key] = body;

                if (mine === catalogSeq) {
                    renderOptions(key);
                    renderServices(key);
                }
            })
            .catch(function () {
                if (mine === catalogSeq) {
                    optionsWrap.innerHTML = '<div class="a2-muted">تعذّر تحميل الخيارات.</div>';
                    servicesWrap.innerHTML = '<div class="a2-muted">تعذّر تحميل الخدمات.</div>';
                }
            });
    }

    function toggleBusinessFields() {
        const isBusiness = typeEl.value === 'business';
        businessWrap.style.display = isBusiness ? 'block' : 'none';

        if (!isBusiness) {
            categoryEl.value = '';
            childEl.innerHTML = '<option value="">-- اختر القسم الفرعي --</option>';
            selectedOptions = [];
            selectedServices = [];
            optionsWrap.innerHTML = '<div class="a2-muted">هذا القسم يظهر فقط للحساب التجاري.</div>';
            servicesWrap.innerHTML = '<div class="a2-muted">هذا القسم يظهر فقط للحساب التجاري.</div>';
        } else {
            renderChildren(categoryEl.value, true);
        }
    }

    typeEl.addEventListener('change', toggleBusinessFields);

    categoryEl.addEventListener('change', function () {
        selectedOptions = [];
        selectedServices = [];
        renderChildren(this.value, false);
    });

    childEl.addEventListener('change', function () {
        selectedOptions = [];
        selectedServices = [];
        showChild(this.value);
    });

    toggleBusinessFields();

    const logoInput = document.getElementById('user-logo-input');
    const logoBox = document.getElementById('logoPreviewBox');

    if (logoInput && logoBox) {
        logoInput.addEventListener('change', function () {
            logoBox.innerHTML = '';

            const file = logoInput.files && logoInput.files[0];
            if (!file) {
                logoBox.innerHTML = '<span class="a2-section-subtitle a2-mb-0">اختر صورة</span>';
                return;
            }

            const img = document.createElement('img');
            img.alt = 'preview';
            img.style.width = '100%';
            img.style.maxHeight = '240px';
            img.style.objectFit = 'cover';
            img.style.borderRadius = '14px';
            img.src = URL.createObjectURL(file);

            logoBox.appendChild(img);
        });
    }
});
</script>