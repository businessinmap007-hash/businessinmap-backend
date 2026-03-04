@extends('admin-v2.layouts.master')

@section('title','View User')

@section('content')
@php
    $id  = (int) $user->id;
    $tab = request()->get('tab', 'info');

    // Back URL: referrer fallback to users index
    $backUrl = url()->previous();
    if (!$backUrl || $backUrl === url()->current()) {
        $backUrl = route('admin.users.index');
    }
     $subsUrl = route('admin.subscriptions.index', ['user_id' => $user->id]);
@endphp
           

<div class="a2-page" style="max-width:1100px;margin:0 auto;">

    {{-- Page Head --}}
    <div class="a2-page-head" style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:14px;">
        <div>
            <h2 class="a2-h2" style="margin:0;">عرض المستخدم <span class="a2-muted">{{ $user->name ?? '—' }}</span></h2>
            
        </div>

        <div style="display:flex;gap:10px;">
            <a class="a2-btn a2-btn-ghost" href="{{ $subsUrl }}">سجل اشتراكات المستخدم </a>
            <a class="a2-btn a2-btn-ghost"href="{{ route('admin.albums.index', ['user_id' => $user->id]) }}"> ألبومات المستخدم</a>
            <a class="a2-btn a2-btn-ghost"href="{{ route('admin.wallet-transactions.user', ['user' => $user->id]) }}">كشف حساب المستخدم</a>
            <a href="{{ route('admin.users.edit',$id) }}" class="a2-btn a2-btn-ghost">تعديل</a>
            <a href="{{ $backUrl }}" class="a2-btn a2-btn-ghost">← العودة</a>
            <a href="{{ route('admin.users.index') }}" class="a2-btn a2-btn-ghost">القائمة</a>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="a2-card" style="margin-bottom:12px;">
        <div style="display:flex;gap:10px;align-items:center;">
            <a class="a2-btn {{ $tab==='info' ? 'a2-btn-primary' : 'a2-btn-ghost' }}"
               href="{{ route('admin.users.show',$id) }}?tab=info">
                Info
            </a>

            <a class="a2-btn {{ $tab==='subs' ? 'a2-btn-primary' : 'a2-btn-ghost' }}"
               href="{{ route('admin.users.show',$id) }}?tab=subs">
                Subscriptions
            </a>

            <a class="a2-btn {{ $tab==='images' ? 'a2-btn-primary' : 'a2-btn-ghost' }}"
               href="{{ route('admin.users.show',$id) }}?tab=images">
                Images
            </a>
        </div>
    </div>

    @if($tab === 'subs')
        {{-- Subscriptions TAB --}}
        <div class="a2-card">
            <div class="a2-card-head">
                <div class="a2-card-title">Subscriptions</div>
                <div class="a2-card-sub">آخر 20 اشتراك</div>
            </div>

            @if(empty($subscriptions) || $subscriptions->count() === 0)
                <div class="a2-muted" style="padding:14px;">لا توجد اشتراكات.</div>
            @else
                <div style="overflow:auto;">
                    <table class="a2-table" style="width:100%;">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>is_active</th>
                            <th>start_at</th>
                            <th>end_at</th>
                            <th>created_at</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($subscriptions as $s)
                            <tr>
                                <td>{{ $s->id }}</td>
                                <td>{{ (int)($s->is_active ?? 0) === 1 ? '1' : '0' }}</td>
                                <td>{{ $s->start_at ?? '—' }}</td>
                                <td>{{ $s->end_at ?? '—' }}</td>
                                <td>{{ $s->created_at ?? '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

    @elseif($tab === 'images')
        {{-- IMAGES TAB --}}
        <div class="a2-card">
            <div class="a2-card-head">
                <div class="a2-card-title">Images</div>
                <div class="a2-card-sub"></div>
            </div>

            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;">

                <div class="a2-card" style="text-align:center;">
                    <div class="a2-card-sub">Image</div>
                    <x-admin-v2.image :path="$user->image" size="120" radius="16px" />
                    <div class="a2-muted" style="margin-top:6px;">
                        {{ $user->image ?: 'No image' }}
                    </div>
                </div>

                <div class="a2-card" style="text-align:center;">
                    <div class="a2-card-sub">Logo</div>
                    <x-admin-v2.image :path="$user->logo" size="120" radius="16px" />
                    <div class="a2-muted" style="margin-top:6px;">
                        {{ $user->logo ?: 'No image' }}
                    </div>
                </div>

                <div class="a2-card" style="text-align:center;">
                    <div class="a2-card-sub">Cover</div>
                    <x-admin-v2.image :path="$user->cover" size="120" radius="16px" />
                    <div class="a2-muted" style="margin-top:6px;">
                        {{ $user->cover ?: 'No image' }}
                    </div>
                </div>

            </div>
        </div>

    @else
        {{-- INFO TAB --}}
        <div class="a2-card">
            <div class="a2-card-head">
                <div class="a2-card-title">User Fields</div>
                <div class="a2-card-sub">كل الحقول حسب جدول users</div>
            </div>

            <div style="overflow:auto;">
                <table class="a2-table" style="width:100%;">
                    <tbody>
                    <tr><th>id</th><td>{{ $user->id }}</td></tr>
                    <tr><th>name</th><td>{{ $user->name }}</td></tr>
                    <tr><th>phone</th><td>{{ $user->phone }}</td></tr>
                    <tr><th>email</th><td>{{ $user->email }}</td></tr>
                    <tr><th>type</th><td>{{ $user->type }}</td></tr>
                    <tr><th>category_id</th><td>{{ $user->category_id }}</td></tr>

                    <tr><th>image</th><td>{{ $user->image }}</td></tr>
                    <tr><th>logo</th><td>{{ $user->logo }}</td></tr>
                    <tr><th>cover</th><td>{{ $user->cover }}</td></tr>

                    <tr><th>about</th><td>{{ $user->about }}</td></tr>

                    <tr><th>activated_at</th><td>{{ $user->activated_at }}</td></tr>
                    <tr><th>action_code</th><td>{{ $user->action_code }}</td></tr>
                    <tr><th>code</th><td>{{ $user->code }}</td></tr>
                    <tr><th>paid_at</th><td>{{ $user->paid_at }}</td></tr>

                    <tr><th>latitude</th><td>{{ $user->latitude }}</td></tr>
                    <tr><th>longitude</th><td>{{ $user->longitude }}</td></tr>

                    <tr><th>balance</th><td>{{ $user->balance }}</td></tr>
                    <tr><th>pin_attempts</th><td>{{ $user->pin_attempts }}</td></tr>
                    <tr><th>pin_locked_until</th><td>{{ $user->pin_locked_until }}</td></tr>

                    <tr><th>created_at</th><td>{{ $user->created_at }}</td></tr>
                    <tr><th>updated_at</th><td>{{ $user->updated_at }}</td></tr>
                    <tr><th>deleted_at</th><td>{{ $user->deleted_at }}</td></tr>

                    <tr><th>api_token</th><td>{{ $user->api_token ? '•••••••• (hidden)' : '' }}</td></tr>
                    <tr><th>remember_token</th><td>{{ $user->remember_token ? '•••••••• (hidden)' : '' }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    @endif

</div>
@endsection
