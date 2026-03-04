@php
    $user = auth()->user();
@endphp




<div class="a2-userbar">
    <div class="a2-user-meta">
        <div class="a2-user-name">
            {{ $user?->name ?? '—' }}
        </div>
        <div class="a2-user-role">
            {{ ucfirst($user?->type ?? '') }}
        </div>
    </div>

    <div class="a2-avatar">
        @if(!empty($user?->avatar))
            <img src="{{ asset($user->avatar) }}" alt="avatar">
        @else
            {{ strtoupper(substr($user?->name ?? 'A',0,1)) }}
        @endif
    </div>

    <form method="POST" action="{{ route('admin.logout') }}">
        @csrf
        <button class="a2-btn a2-btn-ghost" type="submit">
            تسجيل الخروج
        </button>
    </form>
</div>
