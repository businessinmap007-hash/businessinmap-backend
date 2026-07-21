@extends('admin-v2.layouts.master')

@section('title','Levy a fine')
@section('body_class','admin-v2-fines-create')

@section('content')
<div class="a2-page">
    <div class="a2-card" style="padding:14px;max-width:640px;">
        <div class="a2-title" style="font-size:16px;margin-bottom:4px;">{{ __('فرض غرامة') }}</div>
        <div class="a2-hint" style="margin-bottom:14px;">
            {{ __('يُجمَّد ما تسمح به محفظة المستخدم فورًا، وتُفتح نافذة اعتراض. لا يُخصم المبلغ إلا بعد رفض الاعتراض أو انتهاء المهلة.') }}
        </div>

        @if($errors->any())
            <div class="a2-alert a2-alert-danger" style="margin-bottom:12px;">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('admin.fines.store') }}">
            @csrf
            <div class="a2-form-grid">
                <div class="a2-form-group">
                    <label class="a2-label">{{ __('المستخدم') }}</label>
                    <input class="a2-input" type="search" id="fineUserSearch" autocomplete="off"
                           placeholder="{{ __('اكتب اسمًا / هاتفًا / بريدًا') }}" list="fineUsersList">
                    <datalist id="fineUsersList"></datalist>
                    @php $prefillUserId = old('user_id', request('user_id')); @endphp
                    <input type="hidden" name="user_id" id="fineUserId" value="{{ $prefillUserId }}">
                    <div class="a2-hint" id="fineUserPicked" style="margin-top:6px;">
                        @if($prefillUserId) {{ __('المحدد:') }} #{{ $prefillUserId }} @endif
                    </div>
                </div>

                <div class="a2-form-group">
                    <label class="a2-label">{{ __('قيمة الغرامة') }}</label>
                    <input class="a2-input" type="number" step="0.01" min="0.01" name="amount" required value="{{ old('amount') }}">
                </div>

                <div class="a2-form-group">
                    <label class="a2-label">{{ __('مهلة الاعتراض (أيام)') }}</label>
                    <input class="a2-input" type="number" step="1" min="1" max="90" name="appeal_days" value="{{ old('appeal_days', 7) }}">
                </div>

                <div class="a2-form-group" style="grid-column:1/-1;">
                    <label class="a2-label">{{ __('سبب الغرامة') }}</label>
                    <textarea class="a2-input" name="reason" rows="3" required maxlength="1000"
                              placeholder="{{ __('اذكر سبب الاحتيال/الإساءة بوضوح — يراه المستخدم.') }}">{{ old('reason') }}</textarea>
                </div>

                <div class="a2-form-group" style="grid-column:1/-1;">
                    <label class="a2-label" style="display:flex;gap:8px;align-items:center;cursor:pointer;">
                        <input type="checkbox" name="also_ban" value="1" @checked(old('also_ban'))>
                        {{ __('إيقاف الحساب أيضًا (يمنع الدخول ويسجّل الهوية في قائمة الحظر — التجميد يبقى فيمكن الطعن ماليًّا)') }}
                    </label>
                </div>
            </div>

            <div style="margin-top:16px;display:flex;gap:8px;">
                <button class="a2-btn a2-btn-primary" type="submit">{{ __('فرض الغرامة') }}</button>
                <a class="a2-btn a2-btn-ghost" href="{{ route('admin.fines.index') }}">{{ __('إلغاء') }}</a>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    // Root-relative on purpose: an absolute route() URL can carry the wrong host
    // and fail cross-origin, so the search silently returns nothing.
    var searchUrl = @json(route('admin.wallet-ops.users.search', [], false));
    var input = document.getElementById('fineUserSearch');
    var list = document.getElementById('fineUsersList');
    var hidden = document.getElementById('fineUserId');
    var picked = document.getElementById('fineUserPicked');
    var byLabel = {};
    var t;

    input.addEventListener('input', function () {
        var q = input.value.trim();
        // Selecting a datalist option fires 'input' with the option value; map it back to an id.
        if (byLabel[q]) { hidden.value = byLabel[q].id; picked.textContent = '{{ __('المحدد:') }} #' + byLabel[q].id + ' — ' + byLabel[q].name; return; }
        if (q.length < 2) return;
        clearTimeout(t);
        t = setTimeout(function () {
            fetch(searchUrl + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    list.innerHTML = '';
                    byLabel = {};
                    (res.data || []).forEach(function (row) {
                        byLabel[row.label] = row;
                        var o = document.createElement('option');
                        o.value = row.label;
                        list.appendChild(o);
                    });
                })
                .catch(function () {});
        }, 220);
    });
})();
</script>
@endsection
