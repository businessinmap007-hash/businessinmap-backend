@php
    $metaJson = old('meta_json');

    if ($metaJson === null) {
        $meta = is_array($level->meta) ? $level->meta : [];
        $metaJson = count($meta) ? json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '';
    }
@endphp

@if($errors->any())
    <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
@endif

<div class="a2-form-grid">
    <div class="a2-card">
        <h2 class="a2-section-title">{{ __('البيانات الأساسية') }}</h2>

        <div class="a2-field">
            <label class="a2-label">Code</label>
            <input class="a2-input" type="text" name="code" value="{{ old('code', $level->code) }}" required placeholder="client_bronze">
        </div>

        <div class="a2-field">
            <label class="a2-label">{{ __('الاسم عربي') }}</label>
            <input class="a2-input" type="text" name="name_ar" value="{{ old('name_ar', $level->name_ar) }}" placeholder="{{ __('ضمان برونزي') }}">
        </div>

        <div class="a2-field">
            <label class="a2-label">Name EN</label>
            <input class="a2-input" type="text" name="name_en" value="{{ old('name_en', $level->name_en) }}" placeholder="Bronze Guarantee">
        </div>

        <div class="a2-field">
            <label class="a2-label">Target Type</label>
            <select class="a2-select" name="target_type" required>
                <option value="client" {{ old('target_type', $level->target_type) === 'client' ? 'selected' : '' }}>Client</option>
                <option value="business" {{ old('target_type', $level->target_type) === 'business' ? 'selected' : '' }}>Business</option>
            </select>
        </div>

        <div class="a2-field">
            <label class="a2-checkline">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $level->is_active) ? 'checked' : '' }}>
                <span>{{ __('مفعل') }}</span>
            </label>
        </div>
    </div>

    <div class="a2-card">
        <h2 class="a2-section-title">{{ __('الرصيد والتغطية') }}</h2>

        <div class="a2-field">
            <label class="a2-label">Required Locked Amount</label>
            <input class="a2-input" type="number" step="0.01" min="0" name="required_locked_amount" value="{{ old('required_locked_amount', $level->required_locked_amount) }}" required>
        </div>

        <div class="a2-field">
            <label class="a2-label">Pending Coverage Amount</label>
            <input class="a2-input" type="number" step="0.01" min="0" name="pending_coverage_amount" value="{{ old('pending_coverage_amount', $level->pending_coverage_amount) }}" required>
        </div>

        <div class="a2-field">
            <label class="a2-label">Active Coverage Amount</label>
            <input class="a2-input" type="number" step="0.01" min="0" name="active_coverage_amount" value="{{ old('active_coverage_amount', $level->active_coverage_amount) }}" required>
        </div>

        <div class="a2-field">
            <label class="a2-label">Priority</label>
            <input class="a2-input" type="number" min="0" name="priority" value="{{ old('priority', $level->priority) }}" required>
        </div>
    </div>

    <div class="a2-card">
        <h2 class="a2-section-title">{{ __('تعزيز التغطية بالتقييم (Boost)') }}</h2>
        <p class="a2-help">{{ __('تغطية أعلى تُمنح تلقائيًا لأصحاب التقييم الممتاز، وتُسحب فور تراجع السلوك. اترك المبلغ فارغًا لتعطيل التعزيز على هذا المستوى.') }}</p>

        <div class="a2-field">
            <label class="a2-label">Boost Coverage Amount</label>
            <input class="a2-input" type="number" step="0.01" min="0" name="boost_coverage_amount" value="{{ old('boost_coverage_amount', $level->boost_coverage_amount) }}" placeholder="{{ __('فارغ = بدون تعزيز (مثال: active × 1.25)') }}">
        </div>

        <div class="a2-field">
            <label class="a2-label">Boost: Min Completed Operations</label>
            <input class="a2-input" type="number" min="0" name="boost_min_operations" value="{{ old('boost_min_operations', $level->boost_min_operations) }}" placeholder="{{ __('مثال: 5') }}">
        </div>

        <div class="a2-field">
            <label class="a2-label">Boost: Min Success Rate %</label>
            <input class="a2-input" type="number" step="0.01" min="0" max="100" name="boost_min_success_rate" value="{{ old('boost_min_success_rate', $level->boost_min_success_rate) }}" placeholder="{{ __('مثال: 90') }}">
        </div>

        <div class="a2-field">
            <label class="a2-label">Boost: Max Dispute Rate %</label>
            <input class="a2-input" type="number" step="0.01" min="0" max="100" name="boost_max_dispute_rate" value="{{ old('boost_max_dispute_rate', $level->boost_max_dispute_rate) }}" placeholder="{{ __('مثال: 5 — فارغ = بدون حد') }}">
        </div>
    </div>

    <div class="a2-card">
        <h2 class="a2-section-title">{{ __('شروط التأهيل') }}</h2>

        <div class="a2-field">
            <label class="a2-label">Required Completed Operations</label>
            <input class="a2-input" type="number" min="0" name="required_completed_operations" value="{{ old('required_completed_operations', $level->required_completed_operations) }}" required>
        </div>

        <div class="a2-field">
            <label class="a2-label">Required Trust Score</label>
            <input class="a2-input" type="number" step="0.01" min="0" max="100" name="required_trust_score" value="{{ old('required_trust_score', $level->required_trust_score) }}" required>
        </div>

        <div class="a2-field">
            <label class="a2-label">Max Lost Disputes</label>
            <input class="a2-input" type="number" min="0" name="max_lost_disputes" value="{{ old('max_lost_disputes', $level->max_lost_disputes) }}" placeholder="{{ __('فارغ = بدون حد') }}">
        </div>

        <div class="a2-field">
            <label class="a2-label">Max Late Cancellations</label>
            <input class="a2-input" type="number" min="0" name="max_late_cancellations" value="{{ old('max_late_cancellations', $level->max_late_cancellations) }}" placeholder="{{ __('فارغ = بدون حد') }}">
        </div>
    </div>

    <div class="a2-card">
        <h2 class="a2-section-title">Meta JSON</h2>

        <div class="a2-field">
            <label class="a2-label">Meta</label>
            <textarea class="a2-textarea" name="meta_json" rows="12" placeholder='{"note":"optional"}'>{{ $metaJson }}</textarea>
        </div>
    </div>
</div>

<div class="a2-form-actions">
    <button type="submit" class="a2-btn a2-btn-primary">{{ __('حفظ') }}</button>
    <a href="{{ route('admin.guarantee-levels.index') }}" class="a2-btn a2-btn-ghost">{{ __('رجوع') }}</a>
</div>
