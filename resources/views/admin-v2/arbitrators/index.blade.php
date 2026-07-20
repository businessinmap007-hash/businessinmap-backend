@extends('admin-v2.layouts.master')

@section('title','Arbitrators')
@section('body_class','admin-v2-arbitrators')

@section('content')
<div class="a2-page">
    <div class="a2-card" style="padding:14px;">
        <div class="a2-title" style="font-size:16px;margin-bottom:4px;">{{ __('الحُكّام') }}</div>
        <div class="a2-hint" style="margin-bottom:14px;">
            {{ __('الحكم مشرف يملك صلاحية الفصل في النزاعات وتحريك مبلغ الضمان. كل قرار يُسجَّل في سجله ولا يمكن تعديله.') }}
        </div>

        <div style="overflow-x:auto;">
            <table class="a2-table" style="width:100%;">
                <thead>
                    <tr>
                        <th>{{ __('الحكم') }}</th>
                        <th>{{ __('الجلسات') }}</th>
                        <th>{{ __('النتائج') }}</th>
                        <th>{{ __('رسوم التحكيم') }}</th>
                        <th>{{ __('الغرامات المحصّلة') }}</th>
                        <th>{{ __('آخر جلسة') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($arbitrators as $row)
                        <tr>
                            <td>
                                <a href="{{ route('admin.arbitrators.show', $row['user']->id) }}" style="font-weight:800;">
                                    {{ $row['user']->name }}
                                </a>
                                <div class="a2-hint">{{ $row['user']->email }}</div>
                            </td>
                            <td style="font-weight:800;">{{ $row['stats']['sessions'] }}</td>
                            <td>
                                @forelse($row['stats']['by_outcome'] as $outcome => $count)
                                    <span class="a2-hint">{{ $outcome }}: <strong>{{ $count }}</strong></span>@if(! $loop->last) · @endif
                                @empty
                                    <span class="a2-hint">-</span>
                                @endforelse
                            </td>
                            <td style="font-weight:800;">{{ number_format($row['stats']['fees_earned'], 2) }}</td>
                            <td style="font-weight:800;">{{ number_format($row['stats']['fines_collected'], 2) }}</td>
                            <td class="a2-hint">{{ $row['stats']['last_session_at'] ?? '-' }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.arbitrators.demote', $row['user']->id) }}"
                                      onsubmit="return confirm('{{ __('إلغاء صفة الحكم؟ سجل الجلسات سيبقى محفوظًا.') }}');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="a2-btn a2-btn-ghost" type="submit">{{ __('إلغاء التعيين') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="a2-hint">{{ __('لا يوجد حُكّام معيّنون بعد.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="a2-card" style="padding:14px;margin-top:14px;">
        <div class="a2-title" style="font-size:15px;margin-bottom:10px;">{{ __('تعيين حكم') }}</div>

        @if($candidates->isEmpty())
            <div class="a2-hint">{{ __('لا يوجد مشرفون متاحون للتعيين.') }}</div>
        @else
            <form method="POST" action="{{ route('admin.arbitrators.promote') }}">
                @csrf
                <div class="a2-form-grid">
                    <div class="a2-form-group">
                        <label class="a2-label" for="arbitrator-user">{{ __('اختر مشرفًا') }}</label>
                        <select class="a2-select" id="arbitrator-user" name="user_id" required>
                            @foreach($candidates as $candidate)
                                <option value="{{ $candidate->id }}">{{ $candidate->name }} — {{ $candidate->email }}</option>
                            @endforeach
                        </select>
                        <div class="a2-hint" style="margin-top:8px;">
                            {{ __('التعيين يمنح صلاحيات: دخول اللوحة، النزاعات، وتحريك الأموال.') }}
                        </div>
                    </div>

                    <div class="a2-form-group" style="align-self:end;">
                        <button class="a2-btn a2-btn-primary" type="submit">{{ __('تعيين') }}</button>
                    </div>
                </div>
            </form>
        @endif
    </div>
</div>
@endsection
