@extends('admin-v2.layouts.master')

@section('title', 'عرض المستخدم')
@section('body_class', 'admin-v2-users-show')

@section('content')
@php
    $name = (string) ($user->name ?? '');

    $categoryName = $user->category?->name_ar ?: ($user->category?->name_en ?: '—');
    $childName = $user->categoryChild?->name_ar ?: ($user->categoryChild?->name_en ?: '—');

    // ✅ الصورة الأساسية
    $logoPath  = $user->logo ?? null;

    // صور إضافية (اختياري)
    $imagePath = $user->image ?? null;
    $coverPath = $user->cover ?? null;
@endphp

<div class="a2-page a2-page-narrow">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">عرض المستخدم #{{ $user->id }}</h1>
            <div class="a2-page-subtitle">{{ $name ?: '—' }}</div>
        </div>

        <div class="a2-page-actions">
            <a class="a2-btn a2-btn-primary" href="{{ route('admin.users.edit', $user->id) }}">تعديل</a>
            <a class="a2-btn a2-btn-ghost" href="{{ route('admin.users.index') }}">رجوع</a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    {{-- =========================
       صورة المستخدم
       ========================= --}}
    <div class="a2-card">
        <div class="a2-header">
            <h2 class="a2-section-title a2-mb-0">الصورة</h2>
        </div>

        <div style="display:flex;gap:12px;flex-wrap:wrap;">
            @if($logoPath)
                <x-admin-v2.image :path="$logoPath" size="140" radius="16px" />
            @else
                <div class="a2-album-cover-empty" style="width:140px;height:140px;">—</div>
            @endif
        </div>
    </div>

    {{-- =========================
       بيانات أساسية
       ========================= --}}
    <div class="a2-card">
        <div class="a2-header">
            <h2 class="a2-section-title a2-mb-0">البيانات الأساسية</h2>
        </div>

        <div class="a2-form-grid-3">
            <div>
                <label class="a2-label">Name</label>
                <div class="a2-view-field">{{ $user->name ?: '—' }}</div>
            </div>

            <div>
                <label class="a2-label">Type</label>
                <div class="a2-view-field">{{ $user->type ?: '—' }}</div>
            </div>

            <div>
                <label class="a2-label">Email</label>
                <div class="a2-view-field" dir="ltr">{{ $user->email ?: '—' }}</div>
            </div>

            <div>
                <label class="a2-label">Phone</label>
                <div class="a2-view-field" dir="ltr">{{ $user->phone ?: '—' }}</div>
            </div>

            <div>
                <label class="a2-label">Code</label>
                <div class="a2-view-field">{{ $user->code ?: '—' }}</div>
            </div>

            <div>
                <label class="a2-label">Action Code</label>
                <div class="a2-view-field">{{ $user->action_code ?: '—' }}</div>
            </div>

            <div>
                <label class="a2-label">Latitude</label>
                <div class="a2-view-field">{{ $user->latitude ?: '—' }}</div>
            </div>

            <div>
                <label class="a2-label">Longitude</label>
                <div class="a2-view-field">{{ $user->longitude ?: '—' }}</div>
            </div>

            <div>
                <label class="a2-label">Activated At</label>
                <div class="a2-view-field">{{ $user->activated_at ?: '—' }}</div>
            </div>

            <div class="a2-field-full">
                <label class="a2-label">About</label>
                <div class="a2-view-box">{{ $user->about ?: '—' }}</div>
            </div>
        </div>
    </div>

    {{-- =========================
       تصنيف البزنس
       ========================= --}}
    <div class="a2-card">
        <div class="a2-header">
            <h2 class="a2-section-title a2-mb-0">تصنيف البزنس</h2>
        </div>

        <div class="a2-form-grid-3">
            <div>
                <label class="a2-label">Category</label>
                <div class="a2-view-field">{{ $categoryName }}</div>
            </div>

            <div>
                <label class="a2-label">Category Child</label>
                <div class="a2-view-field">{{ $childName }}</div>
            </div>

            <div>
                <label class="a2-label">Options Count</label>
                <div class="a2-view-field">{{ collect($user->options ?? [])->count() }}</div>
            </div>
        </div>

        <div class="a2-divider"></div>

        @if(($groupedOptions ?? collect())->count())
            <div style="display:grid;gap:14px;">
                @foreach($groupedOptions as $groupKey => $rows)
                    @php
                        $groupTitle = $groupKey === 'ungrouped' ? 'بدون مجموعة' : ('Group #' . $groupKey);
                    @endphp

                    <div class="a2-card a2-card--soft a2-card--tight">
                        <div style="font-weight:900;margin-bottom:10px;">{{ $groupTitle }}</div>
                        <div class="a2-view-box" style="min-height:auto;">
                            {{ collect($rows)->map(fn($opt) => $opt->name_ar ?: ($opt->name_en ?: ('#' . $opt->id)))->implode(' ، ') ?: '—' }}
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="a2-view-box" style="min-height:auto;">لا توجد خيارات مختارة.</div>
        @endif
    </div>

    {{-- =========================
       الاشتراكات
       ========================= --}}
    <div class="a2-card">
        <div class="a2-header">
            <h2 class="a2-section-title a2-mb-0">الاشتراكات الأخيرة</h2>
        </div>

        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>الحالة</th>
                        <th>بدأ</th>
                        <th>ينتهي</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($subscriptions as $sub)
                        <tr>
                            <td>{{ $sub->id }}</td>
                            <td>
                                @if((int) ($sub->is_active ?? 0) === 1)
                                    <span class="a2-pill a2-pill-sub-active">نشط</span>
                                @else
                                    <span class="a2-pill a2-pill-sub-none">غير نشط</span>
                                @endif
                            </td>
                            <td>{{ $sub->starts_at ?? '—' }}</td>
                            <td>{{ $sub->ends_at ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="a2-empty-cell">لا توجد اشتراكات</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection