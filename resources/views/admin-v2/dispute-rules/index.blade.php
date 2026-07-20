@extends('admin-v2.layouts.master')

@section('title','Dispute rules')
@section('body_class','admin-v2-dispute-rules')

@section('content')
<div class="a2-page">
    <div class="a2-card" style="padding:14px;">
        <div class="a2-title" style="font-size:16px;margin-bottom:4px;">{{ __('قواعد النزاع') }}</div>
        <div class="a2-hint">
            {{ __('هذه الشروط تُعرض على طرفَي النزاع، ولا يستطيع أيٌّ منهما الكتابة في الغرفة قبل الموافقة عليها.') }}
        </div>

        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:14px;">
            <div>
                <div class="a2-hint">{{ __('النسخة السارية') }}</div>
                <div style="font-weight:800;">{{ $current['version'] }}</div>
            </div>
            <div>
                <div class="a2-hint">{{ __('المصدر') }}</div>
                <div style="font-weight:800;">
                    {{ $active ? __('منشورة من اللوحة') : __('النسخة المرفقة بالنظام') }}
                </div>
            </div>
            <div>
                <div class="a2-hint">{{ __('نُشرت في') }}</div>
                <div style="font-weight:800;">{{ optional($active?->published_at)->format('Y-m-d H:i') ?: '-' }}</div>
            </div>
        </div>
    </div>

    <div class="a2-card" style="padding:14px;margin-top:14px;">
        <div class="a2-title" style="font-size:15px;margin-bottom:6px;">{{ __('نشر نسخة جديدة') }}</div>

        <div class="a2-hint" style="margin-bottom:12px;color:#b42318;">
            {{ __('تنبيه: النشر لا يعدّل النسخة الحالية بل ينشئ نسخة تالية، ويُلغي كل الموافقات السابقة — على كل طرف في كل نزاع مفتوح أن يوافق من جديد قبل أن يكتب. هذا مقصود: من وافق على نصّ قديم لم يوافق على النص الجديد.') }}
        </div>

        <form method="POST" action="{{ route('admin.dispute-rules.store') }}"
              onsubmit="return confirm('{{ __('تأكيد النشر؟ ستُلغى كل الموافقات الحالية.') }}');">
            @csrf

            <div class="a2-form-group">
                <label class="a2-label" for="rules-title">{{ __('عنوان الوثيقة') }}</label>
                <input class="a2-input" id="rules-title" name="title" maxlength="255" required
                       value="{{ old('title', $current['title']) }}">
            </div>

            @foreach($current['sections'] as $i => $section)
                <div class="a2-form-group" style="margin-top:14px;">
                    <label class="a2-label">{{ __('عنوان القسم') }} {{ $i + 1 }}</label>
                    <input class="a2-input" name="sections[{{ $i }}][title]" maxlength="255" required
                           value="{{ old("sections.$i.title", $section['title']) }}">

                    <label class="a2-label" style="margin-top:8px;">{{ __('البنود — بند في كل سطر') }}</label>
                    <textarea class="a2-textarea" name="sections[{{ $i }}][clauses]" rows="7" required>{{ old("sections.$i.clauses", implode("\n", $section['clauses'])) }}</textarea>
                </div>
            @endforeach

            {{-- A spare section, so a third one can be added without a developer. --}}
            @php $spare = count($current['sections']); @endphp
            <div class="a2-form-group" style="margin-top:14px;">
                <label class="a2-label">{{ __('قسم إضافي (اختياري)') }}</label>
                <input class="a2-input" name="sections[{{ $spare }}][title]" maxlength="255"
                       value="{{ old("sections.$spare.title") }}" placeholder="{{ __('اتركه فارغًا إن لم تحتجه') }}">
                <textarea class="a2-textarea" name="sections[{{ $spare }}][clauses]" rows="4"
                          placeholder="{{ __('بند في كل سطر') }}">{{ old("sections.$spare.clauses") }}</textarea>
            </div>

            <button class="a2-btn a2-btn-primary" style="margin-top:14px;" type="submit">
                {{ __('نشر نسخة جديدة') }}
            </button>
        </form>
    </div>

    <div class="a2-card" style="padding:14px;margin-top:14px;">
        <div class="a2-title" style="font-size:15px;margin-bottom:10px;">{{ __('النسخ السابقة') }}</div>

        <div class="a2-hint" style="margin-bottom:10px;">
            {{ __('محفوظة كما هي: موافقة كل طرف مرتبطة برقم نسخة، وهذه هي الطريقة الوحيدة لمعرفة ما وافق عليه فعلًا.') }}
        </div>

        <div style="overflow-x:auto;">
            <table class="a2-table" style="width:100%;">
                <thead>
                    <tr>
                        <th>{{ __('النسخة') }}</th>
                        <th>{{ __('العنوان') }}</th>
                        <th>{{ __('نشرها') }}</th>
                        <th>{{ __('التاريخ') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($versions as $version)
                        <tr>
                            <td style="font-weight:800;">
                                <a href="{{ route('admin.dispute-rules.show', $version->id) }}">{{ $version->version }}</a>
                                @if($active && $active->id === $version->id)
                                    <span class="a2-hint">({{ __('سارية') }})</span>
                                @endif
                            </td>
                            <td>{{ $version->title }}</td>
                            <td>{{ $version->publishedBy?->name ?? '-' }}</td>
                            <td class="a2-hint">{{ optional($version->published_at)->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="a2-hint">{{ __('لم تُنشر أي نسخة بعد — النظام يستخدم النسخة المرفقة به.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
