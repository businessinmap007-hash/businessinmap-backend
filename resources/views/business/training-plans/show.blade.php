@extends('business.layouts.master')

@section('title', $plan->title)

@php
    $statusLabels = [
        'active' => __('نشطة'),
        'paused' => __('متوقفة'),
        'completed' => __('منتهية'),
        'cancelled' => __('ملغاة'),
    ];

    $dayLabels = [
        0 => __('الأحد'), 1 => __('الاثنين'), 2 => __('الثلاثاء'), 3 => __('الأربعاء'),
        4 => __('الخميس'), 5 => __('الجمعة'), 6 => __('السبت'),
    ];

    $mealLabels = [
        'breakfast' => __('فطار'),
        'lunch' => __('غداء'),
        'dinner' => __('عشاء'),
        'snack' => __('سناك'),
    ];

    // A signed delta reads as an improvement or a warning depending on the
    // measure: muscle up is good, fat up is not. The arrow says the direction;
    // the colour says which way is the right way for THIS number.
    $goodWhenDown = ['fat_percent' => true, 'weight_kg' => true];
@endphp

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">{{ $plan->title }}</h1>
        <div class="a2-page-subtitle">
            {{ optional($plan->client)->name }}
            @if(optional($plan->client)->phone) — {{ $plan->client->phone }} @endif
            · <span class="a2-pill {{ $plan->status === 'active' ? 'a2-pill-success' : 'a2-pill-gray' }}">{{ $statusLabels[$plan->status] ?? $plan->status }}</span>
        </div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('business.training-plans.index') }}" class="a2-btn a2-btn-ghost">{{ __('رجوع') }}</a>
    </div>
</div>

@if(session('success'))
    <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="a2-alert a2-alert-danger">
        <ul style="margin:0;padding-inline-start:18px;">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

{{-- ── الخطة نفسها ───────────────────────────────────────────────── --}}
<form method="POST" action="{{ route('business.training-plans.update', $plan->id) }}" class="a2-card">
    @csrf
    @method('PUT')

    <div style="display:grid;grid-template-columns:2fr 2fr 1fr;gap:12px;">
        <div class="a2-field">
            <label class="a2-label">{{ __('عنوان الخطة') }}</label>
            <input class="a2-input" name="title" value="{{ $plan->title }}" maxlength="200" required>
        </div>
        <div class="a2-field">
            <label class="a2-label">{{ __('الهدف') }}</label>
            <input class="a2-input" name="goal" value="{{ $plan->goal }}" maxlength="200">
        </div>
        <div class="a2-field">
            <label class="a2-label">{{ __('الحالة') }}</label>
            <select class="a2-select" name="status">
                @foreach($statusLabels as $key => $label)
                    <option value="{{ $key }}" @selected($plan->status === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="a2-field">
        <label class="a2-label">{{ __('ملاحظات') }}</label>
        <textarea class="a2-input" name="notes" rows="2" maxlength="2000">{{ $plan->notes }}</textarea>
    </div>

    <div class="a2-form-actions">
        <button class="a2-btn a2-btn-primary" type="submit">{{ __('حفظ') }}</button>
    </div>
</form>

{{-- ── التمارين ──────────────────────────────────────────────────── --}}
<div class="a2-card">
    <h2 class="a2-card-title">{{ __('التمارين') }}</h2>

    <div class="a2-table-wrap">
        <table class="a2-table">
            <thead>
                <tr>
                    <th>{{ __('اليوم') }}</th>
                    <th>{{ __('التمرين') }}</th>
                    <th>{{ __('مجموعات × عدّات') }}</th>
                    <th>{{ __('راحة') }}</th>
                    <th>{{ __('ملاحظات') }}</th>
                    <th>{{ __('صور') }}</th>
                    <th class="a2-text-right"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($plan->exercises as $exercise)
                    <tr>
                        <td>{{ $exercise->day_of_week === null ? '—' : ($dayLabels[$exercise->day_of_week] ?? $exercise->day_of_week) }}</td>
                        <td>{{ $exercise->name }}</td>
                        <td>{{ $exercise->sets ?: '—' }} × {{ $exercise->reps ?: '—' }}</td>
                        <td>{{ $exercise->rest_seconds ? $exercise->rest_seconds . ' ' . __('ث') : '—' }}</td>
                        <td>{{ $exercise->notes ?: '—' }}</td>
                        <td>{{ $exercise->images->count() }}</td>
                        <td class="a2-text-right">
                            <form method="POST" action="{{ route('business.training-plans.exercises.destroy', [$plan->id, $exercise->id]) }}"
                                  onsubmit="return confirm('{{ __('حذف التمرين؟ صوره تُحذف معه.') }}');">
                                @csrf
                                @method('DELETE')
                                <button class="a2-btn a2-btn-sm a2-btn-danger" type="submit">{{ __('حذف') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="a2-empty">{{ __('لا تمارين بعد.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <form method="POST" action="{{ route('business.training-plans.exercises.store', $plan->id) }}"
          style="display:grid;grid-template-columns:1fr 2fr 80px 100px 100px 2fr auto;gap:8px;align-items:end;margin-top:12px;">
        @csrf
        <div class="a2-field">
            <label class="a2-label">{{ __('اليوم') }}</label>
            <select class="a2-select" name="day_of_week">
                <option value="">—</option>
                @foreach($dayLabels as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="a2-field">
            <label class="a2-label">{{ __('التمرين') }}</label>
            <input class="a2-input" name="name" maxlength="200" required>
        </div>
        <div class="a2-field">
            <label class="a2-label">{{ __('مجموعات') }}</label>
            <input class="a2-input" type="number" name="sets" min="0">
        </div>
        <div class="a2-field">
            <label class="a2-label">{{ __('عدّات') }}</label>
            <input class="a2-input" name="reps" maxlength="40" placeholder="12">
        </div>
        <div class="a2-field">
            <label class="a2-label">{{ __('راحة (ث)') }}</label>
            <input class="a2-input" type="number" name="rest_seconds" min="0">
        </div>
        <div class="a2-field">
            <label class="a2-label">{{ __('ملاحظات') }}</label>
            <input class="a2-input" name="notes" maxlength="255">
        </div>
        <button class="a2-btn a2-btn-primary" type="submit">{{ __('إضافة') }}</button>
    </form>
</div>

{{-- ── النظام الغذائي ────────────────────────────────────────────── --}}
<div class="a2-card">
    <h2 class="a2-card-title">{{ __('النظام الغذائي') }}</h2>

    <div class="a2-table-wrap">
        <table class="a2-table">
            <thead>
                <tr>
                    <th>{{ __('الوجبة') }}</th>
                    <th>{{ __('الصنف') }}</th>
                    <th>{{ __('سعرات') }}</th>
                    <th>{{ __('ملاحظات') }}</th>
                    <th>{{ __('صور') }}</th>
                    <th class="a2-text-right"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($plan->meals as $meal)
                    <tr>
                        <td>{{ $mealLabels[$meal->meal_type] ?? $meal->meal_type }}</td>
                        <td>{{ $meal->name }}</td>
                        <td>{{ $meal->calories ?: '—' }}</td>
                        <td>{{ $meal->notes ?: '—' }}</td>
                        <td>{{ $meal->images->count() }}</td>
                        <td class="a2-text-right">
                            <form method="POST" action="{{ route('business.training-plans.meals.destroy', [$plan->id, $meal->id]) }}"
                                  onsubmit="return confirm('{{ __('حذف الوجبة؟ صورها تُحذف معها.') }}');">
                                @csrf
                                @method('DELETE')
                                <button class="a2-btn a2-btn-sm a2-btn-danger" type="submit">{{ __('حذف') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="a2-empty">{{ __('لا وجبات بعد.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <form method="POST" action="{{ route('business.training-plans.meals.store', $plan->id) }}"
          style="display:grid;grid-template-columns:1fr 2fr 100px 2fr auto;gap:8px;align-items:end;margin-top:12px;">
        @csrf
        <div class="a2-field">
            <label class="a2-label">{{ __('الوجبة') }}</label>
            <select class="a2-select" name="meal_type" required>
                @foreach($mealLabels as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="a2-field">
            <label class="a2-label">{{ __('الصنف') }}</label>
            <input class="a2-input" name="name" maxlength="200" required>
        </div>
        <div class="a2-field">
            <label class="a2-label">{{ __('سعرات') }}</label>
            <input class="a2-input" type="number" name="calories" min="0">
        </div>
        <div class="a2-field">
            <label class="a2-label">{{ __('ملاحظات') }}</label>
            <input class="a2-input" name="notes" maxlength="255">
        </div>
        <button class="a2-btn a2-btn-primary" type="submit">{{ __('إضافة') }}</button>
    </form>
</div>

{{-- ── التقرير الشهري ────────────────────────────────────────────── --}}
<div class="a2-card">
    <h2 class="a2-card-title">{{ __('التقرير الشهري — العضل والدهون والمياه') }}</h2>
    <div class="a2-help" style="margin-bottom:10px;">
        {{ __('الميزان عندك، فأنت من يسجّل. العميل يقرأ فقط. قياس ثانٍ في نفس الشهر يُحدِّث ولا يضيف.') }}
    </div>

    <div class="a2-table-wrap">
        <table class="a2-table">
            <thead>
                <tr>
                    <th>{{ __('الشهر') }}</th>
                    <th>{{ __('الوزن') }}</th>
                    <th>{{ __('كتلة عضلية') }}</th>
                    <th>{{ __('دهون %') }}</th>
                    <th>{{ __('مياه %') }}</th>
                    <th>{{ __('ملاحظات') }}</th>
                    <th class="a2-text-right"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($reports as $entry)
                    @php $report = $entry['report']; $change = $entry['change']; @endphp
                    <tr>
                        <td>{{ optional($report->for_month)->format('Y-m') }}</td>
                        @foreach(['weight_kg', 'muscle_mass_kg', 'fat_percent', 'water_percent'] as $key)
                            @php
                                $delta = $change[$key] ?? null;
                                $down = ($goodWhenDown[$key] ?? false);
                                $good = $delta === null || $delta == 0 ? null : ($down ? $delta < 0 : $delta > 0);
                            @endphp
                            <td>
                                {{ $report->{$key} === null ? '—' : $report->{$key} }}
                                @if($delta !== null && $delta != 0)
                                    <span class="a2-pill {{ $good ? 'a2-pill-success' : 'a2-pill-gray' }}">
                                        {{ $delta > 0 ? '▲ +' : '▼ ' }}{{ $delta }}
                                    </span>
                                @endif
                            </td>
                        @endforeach
                        <td>{{ $report->notes ?: '—' }}</td>
                        <td class="a2-text-right">
                            <form method="POST" action="{{ route('business.training-plans.body-reports.destroy', [$plan->id, $report->id]) }}"
                                  onsubmit="return confirm('{{ __('حذف تقرير الشهر؟') }}');">
                                @csrf
                                @method('DELETE')
                                <button class="a2-btn a2-btn-sm a2-btn-danger" type="submit">{{ __('حذف') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="a2-empty">{{ __('لا تقارير بعد. سجّل قياس هذا الشهر ليظهر التغيّر في الشهر القادم.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <form method="POST" action="{{ route('business.training-plans.body-reports.store', $plan->id) }}"
          style="display:grid;grid-template-columns:repeat(5, 1fr) 2fr auto;gap:8px;align-items:end;margin-top:12px;">
        @csrf
        <div class="a2-field">
            <label class="a2-label">{{ __('الشهر') }}</label>
            <input class="a2-input" type="date" name="for_month" value="{{ now()->toDateString() }}">
        </div>
        <div class="a2-field">
            <label class="a2-label">{{ __('الوزن (كجم)') }}</label>
            <input class="a2-input" type="number" step="0.01" name="weight_kg" min="1" max="600">
        </div>
        <div class="a2-field">
            <label class="a2-label">{{ __('كتلة عضلية (كجم)') }}</label>
            <input class="a2-input" type="number" step="0.01" name="muscle_mass_kg" min="0" max="200">
        </div>
        <div class="a2-field">
            <label class="a2-label">{{ __('دهون %') }}</label>
            <input class="a2-input" type="number" step="0.01" name="fat_percent" min="0" max="100">
        </div>
        <div class="a2-field">
            <label class="a2-label">{{ __('مياه %') }}</label>
            <input class="a2-input" type="number" step="0.01" name="water_percent" min="0" max="100">
        </div>
        <div class="a2-field">
            <label class="a2-label">{{ __('ملاحظات') }}</label>
            <input class="a2-input" name="notes" maxlength="1000">
        </div>
        <button class="a2-btn a2-btn-primary" type="submit">{{ __('حفظ القياس') }}</button>
    </form>
</div>
@endsection
