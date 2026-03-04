@extends('admin-v2.layouts.master')

@section('title','Edit User')

@section('content')
@php
    $id = (int) $user->id;
    $isTrashed = method_exists($user, 'trashed') ? (bool)$user->trashed() : false;

    $imagePath = $user->image ?? null;
    $logoPath  = $user->logo ?? null;
    $coverPath = $user->cover ?? null;
@endphp

<div class="a2-page" style="max-width:1100px;margin:0 auto;">

    <div class="a2-page-head" style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:14px;">
        <div>
            <h2 class="a2-h2" style="margin:0;">
                تعديل المستخدم <span class="a2-muted">#{{ $id }}</span>
            </h2>
            <div class="a2-sub" style="margin-top:6px;">
                @if($isTrashed)
                    <span class="a2-badge a2-badge-danger">محذوف (Soft)</span>
                @else
                    <span class="a2-badge a2-badge-success">نشط</span>
                @endif
            </div>
        </div>

        <div style="display:flex;gap:10px;">
            <a href="{{ route('admin.users.show',$id) }}" class="a2-btn a2-btn-ghost">عرض المستخدم</a>
            <a href="{{ route('admin.users.index') }}" class="a2-btn a2-btn-ghost">القائمة</a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success" style="margin-bottom:12px;">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger" style="margin-bottom:12px;">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.users.update',$id) }}" class="a2-card">
        @csrf
        @method('PUT')

        {{-- Thumbnails --}}
        <div class="a2-card-head" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
            <div>
                <div class="a2-card-title">الصور الحالية</div>
                <div class="a2-card-sub">Image / Logo / Cover</div>
            </div>

            <div style="display:flex;gap:10px;align-items:center;">
                <x-admin-v2.image :path="$imagePath" size="54" radius="14px" />
                <x-admin-v2.image :path="$logoPath"  size="54" radius="14px" />
                <x-admin-v2.image :path="$coverPath" size="54" radius="14px" />
            </div>
        </div>

        {{-- Basic fields --}}
        <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;">
            <div>
                <label class="a2-label">Name</label>
                <input class="a2-input" name="name" value="{{ old('name',$user->name) }}">
            </div>

            <div>
                <label class="a2-label">Phone</label>
                <input class="a2-input" name="phone" value="{{ old('phone',$user->phone) }}">
            </div>

            <div>
                <label class="a2-label">Email</label>
                <input class="a2-input" name="email" value="{{ old('email',$user->email) }}">
            </div>

            <div>
                <label class="a2-label">Type</label>
                <select class="a2-input" name="type">
                    <option value="client"   @selected(old('type',$user->type)==='client')>client</option>
                    <option value="business" @selected(old('type',$user->type)==='business')>business</option>
                    <option value="admin"    @selected(old('type',$user->type)==='admin')>admin</option>
                </select>
            </div>

            <div>
                <label class="a2-label">Category ID</label>
                <input class="a2-input" name="category_id" value="{{ old('category_id',$user->category_id) }}">
            </div>

            <div>
                <label class="a2-label">Code</label>
                <input class="a2-input" name="code" value="{{ old('code',$user->code) }}">
            </div>

            <div>
                <label class="a2-label">Latitude</label>
                <input class="a2-input" name="latitude" value="{{ old('latitude',$user->latitude) }}">
            </div>

            <div>
                <label class="a2-label">Longitude</label>
                <input class="a2-input" name="longitude" value="{{ old('longitude',$user->longitude) }}">
            </div>

            <div>
                <label class="a2-label">Action Code</label>
                <input class="a2-input" name="action_code" value="{{ old('action_code',$user->action_code) }}">
            </div>

            <div style="grid-column:1/-1;">
                <label class="a2-label">About</label>
                <textarea class="a2-input" name="about" rows="3">{{ old('about',$user->about) }}</textarea>
            </div>
        </div>

        <div style="height:1px;background:var(--a2-border);margin:18px 0;"></div>

        {{-- Uploaders ONLY (no text inputs) --}}
        <div class="a2-card-sub" style="margin-bottom:10px;">
            رفع الصور (بنفس نظام Category)
        </div>

        <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;">
            <x-admin-v2.image-upload name="image" label="Image" :path="$user->image" />
            <x-admin-v2.image-upload name="logo"  label="Logo"  :path="$user->logo" />
            <x-admin-v2.image-upload name="cover" label="Cover" :path="$user->cover" />
        </div>

        <div style="height:1px;background:var(--a2-border);margin:18px 0;"></div>

        {{-- System info (readonly) --}}
        <div class="a2-card-sub" style="margin-bottom:10px;">معلومات النظام (عرض فقط)</div>

        <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;">
            <div>
                <label class="a2-label">Activated At</label>
                <input class="a2-input" value="{{ $user->activated_at }}" readonly>
            </div>
            <div>
                <label class="a2-label">Paid At</label>
                <input class="a2-input" value="{{ $user->paid_at }}" readonly>
            </div>
            <div>
                <label class="a2-label">Balance</label>
                <input class="a2-input" value="{{ $user->balance }}" readonly>
            </div>
            <div>
                <label class="a2-label">Pin Attempts</label>
                <input class="a2-input" value="{{ $user->pin_attempts }}" readonly>
            </div>
            <div>
                <label class="a2-label">Pin Locked Until</label>
                <input class="a2-input" value="{{ $user->pin_locked_until }}" readonly>
            </div>
            <div>
                <label class="a2-label">Deleted At</label>
                <input class="a2-input" value="{{ $user->deleted_at }}" readonly>
            </div>
            <div style="grid-column:1/-1;">
                <label class="a2-label">API Token</label>
                <input class="a2-input" value="{{ $user->api_token ? '•••••••••• (hidden)' : '' }}" readonly>
            </div>
            <div style="grid-column:1/-1;">
                <label class="a2-label">Remember Token</label>
                <input class="a2-input" value="{{ $user->remember_token ? '•••••••••• (hidden)' : '' }}" readonly>
            </div>
        </div>

        <div style="height:1px;background:var(--a2-border);margin:18px 0;"></div>

        {{-- Password --}}
        <div class="a2-card-sub" style="margin-bottom:10px;">تغيير كلمة المرور (اختياري)</div>

        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;">
            <div>
                <label class="a2-label">Password</label>
                <input type="password" class="a2-input" name="password">
            </div>
            <div>
                <label class="a2-label">Confirm Password</label>
                <input type="password" class="a2-input" name="password_confirmation">
            </div>
        </div>

        <div style="margin-top:16px;display:flex;gap:10px;">
            <button class="a2-btn a2-btn-primary" type="submit">حفظ</button>
            <a class="a2-btn a2-btn-ghost" href="{{ route('admin.users.show',$id) }}">إلغاء</a>
        </div>
    </form>
</div>
@endsection
