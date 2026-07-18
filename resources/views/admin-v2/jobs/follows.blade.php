@extends('admin-v2.layouts.master')

@section('title','متابعات الوظائف')
@section('body_class','admin-v2-job-follows')

@section('content')
@php
    $cards = [
        ['label' => 'وظائف معلنة', 'value' => $stats['jobs_posted']],
        ['label' => 'مفتوحة الآن', 'value' => $stats['jobs_open']],
        ['label' => 'إجمالي المتقدمين', 'value' => $stats['applicants_total']],
        ['label' => 'تم قبولهم', 'value' => $stats['approved_total']],
        ['label' => 'متابعات نشطة', 'value' => $stats['follows_active']],
        ['label' => 'جهات توظّف', 'value' => $stats['businesses_hiring']],
    ];
@endphp

<div class="a2-page">

    <div class="a2-card">
        <div class="a2-header">
            <h2 class="a2-title">متابعات الوظائف</h2>
            <a class="a2-btn a2-btn-ghost" href="{{ route('admin.jobs.index') }}">الوظائف</a>
        </div>

        <div class="jf-cards">
            @foreach($cards as $c)
                <div class="jf-card">
                    <div class="jf-card-value">{{ number_format((int) $c['value']) }}</div>
                    <div class="jf-card-label">{{ $c['label'] }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="a2-card">
        <div class="a2-header">
            <h2 class="a2-title">أكثر المجالات متابعة</h2>
        </div>

        <div class="a2-hint" style="margin-bottom:10px;">
            مجال بمتابعين كُثر و«وظائف مفتوحة = ٠» يعني طلب بدون عرض.
        </div>

        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                <tr>
                    <th>التصنيف</th>
                    <th>التخصص</th>
                    <th style="width:140px;">عدد المتابعين</th>
                    <th style="width:160px;">وظائف مفتوحة</th>
                </tr>
                </thead>
                <tbody>
                @forelse($topFields as $f)
                    <tr>
                        <td>{{ $f['category'] ?: '—' }}</td>
                        <td>{{ $f['child'] ?: 'التصنيف كامل' }}</td>
                        <td>{{ number_format($f['followers']) }}</td>
                        <td>
                            @if($f['open_jobs'] === 0)
                                <span class="jf-gap">لا يوجد</span>
                            @else
                                {{ number_format($f['open_jobs']) }}
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="a2-empty-cell">لا توجد متابعات بعد</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="a2-card">
        <div class="a2-header">
            <h2 class="a2-title">كل المتابعات</h2>
        </div>

        <form method="GET" action="{{ route('admin.job-follows.index') }}" class="a2-toolbar">
            <div class="a2-filters">
                <input class="a2-input" name="q" value="{{ $q }}" placeholder="بحث باسم أو هاتف المتابِع">
                <div class="a2-actionsbar">
                    <button type="submit" class="a2-btn a2-btn-primary">تطبيق</button>
                    <a class="a2-btn a2-btn-ghost" href="{{ route('admin.job-follows.index') }}">تفريغ</a>
                </div>
            </div>
        </form>

        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                <tr>
                    <th style="width:90px;">ID</th>
                    <th>المتابِع</th>
                    <th>التصنيف</th>
                    <th>التخصص</th>
                    <th style="width:100px;">نشطة</th>
                    <th style="width:170px;">آخر إشعار</th>
                </tr>
                </thead>
                <tbody>
                @forelse($follows as $f)
                    <tr>
                        <td>{{ $f->id }}</td>
                        <td class="a2-clip a2-clip--name">{{ $f->user?->name ?: '—' }}</td>
                        <td>{{ $f->category?->name_ar ?: ($f->category?->name_en ?: '—') }}</td>
                        <td>{{ $f->categoryChild?->name_ar ?: ($f->categoryChild?->name_en ?: 'التصنيف كامل') }}</td>
                        <td>{{ $f->is_active ? 'نعم' : 'لا' }}</td>
                        <td dir="ltr">{{ $f->last_matched_at ? $f->last_matched_at->format('Y-m-d H:i') : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="a2-empty-cell">لا يوجد بيانات</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if(method_exists($follows, 'links'))
            <div class="a2-paginate">{{ $follows->links() }}</div>
        @endif
    </div>

</div>

<style>
  .jf-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px}
  .jf-card{
    border:1px solid var(--a2-border,#eee);
    border-radius:14px;
    padding:14px 16px;
    text-align:center;
    background:var(--a2-card,#fff);
  }
  .jf-card-value{font-size:26px;font-weight:900;line-height:1.2}
  .jf-card-label{margin-top:4px;font-size:13px;opacity:.75}
  .jf-gap{color:#c0392b;font-weight:700}
</style>
@endsection
