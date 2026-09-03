@extends('admin-v2.layouts.master')

@section('title','Chat Access Settings')
@section('body_class','admin-v2-chat-access-settings')

@section('content')
<div class="a2-page">
  <div class="a2-card" style="max-width:560px">
    <div class="a2-header">
      <div>
        <h2 class="a2-title">{{ __('إعدادات الاطلاع على المحادثات') }}</h2>
        <div class="a2-hint">{{ __('محتوى أي محادثة على المنصة مشفّر ولا يظهر لأي مشرف إلا بموافقة طرفيها، أو بعد اطّلاع عدد كافٍ من المشرفين — العدد هنا هو النِّصاب المطلوب.') }}</div>
      </div>
    </div>

    @if(session('success'))
      <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
      <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
    @endif

    <form method="post" action="{{ route('admin.chat-access-settings.update') }}">
      @csrf
      @method('PUT')

      <div class="a2-field" style="margin-bottom:18px">
        <label class="a2-label" for="admin_quorum">{{ __('عدد المشرفين المطلوب (النِّصاب)') }}</label>
        <input class="a2-input" id="admin_quorum" type="number" min="1" max="50" name="admin_quorum"
               value="{{ old('admin_quorum', $accessSetting->admin_quorum) }}">
        <div class="a2-hint">{{ __('عندما يوافق هذا العدد من المشرفين — كلٌّ على حدة — تُفتح المحادثة لهم جميعًا دون الحاجة لموافقة الطرفين.') }}</div>
      </div>

      <div class="a2-actionsbar">
        <button class="a2-btn a2-btn-primary" type="submit">{{ __('حفظ') }}</button>
      </div>
    </form>
  </div>
</div>
@endsection
