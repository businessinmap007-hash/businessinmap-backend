@php
    $user = auth()->user();
@endphp

<div class="a2-userbar" style="display:flex;align-items:center;gap:10px;">
    <div class="a2-user-meta" style="text-align:end;">
        <div class="a2-user-name" style="font-weight:700;">
            {{ $user?->name ?? '—' }}
        </div>
        <div class="a2-user-role a2-hint">
            {{ ucfirst($user?->type ?? '') }}
        </div>
    </div>

    <div class="a2-avatar" style="width:40px;height:40px;border-radius:999px;overflow:hidden;display:flex;align-items:center;justify-content:center;background:#e5e7eb;font-weight:700;">
        @if(!empty($user?->avatar))
            <img src="{{ asset($user->avatar) }}" alt="avatar" style="width:100%;height:100%;object-fit:cover;">
        @else
            {{ strtoupper(substr($user?->name ?? 'A', 0, 1)) }}
        @endif
    </div>

    <form method="POST" action="{{ route('admin.logout') }}" style="margin:0;">
        @csrf
        <button class="a2-btn a2-btn-ghost" type="submit">
            تسجيل الخروج
        </button>
    </form>
</div>