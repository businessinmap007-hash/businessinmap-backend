@php
    use Illuminate\Support\Str;

    $user = auth()->user();

    $userName = trim((string)($user?->name ?? ''));
    $userType = trim((string)($user?->type ?? ''));

    $displayName = $userName !== '' ? Str::limit($userName, 24) : 'Admin';
    $displayType = $userType !== '' ? ucfirst($userType) : 'Admin';

    $firstLetter = $userName !== ''
        ? mb_substr($userName, 0, 1, 'UTF-8')
        : 'A';

    $userInitial = mb_strtoupper($firstLetter, 'UTF-8');

    $avatarPath = $user?->avatar
        ?? $user?->logo
        ?? $user?->image
        ?? null;

    $avatarSrc = null;

    if (! empty($avatarPath)) {
        $path = ltrim((string) $avatarPath, '/');

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $avatarSrc = $path;
        } else {
            $avatarSrc = asset($path);
        }
    }
@endphp

<div class="a2-userbar">
    <div class="a2-user-meta">
        <div class="a2-user-name" title="{{ $userName !== '' ? $userName : 'Admin' }}">
            {{ $displayName }}
        </div>

        <div class="a2-user-role a2-hint">
            {{ $displayType }}
        </div>
    </div>

    <div class="a2-avatar" title="{{ $userName !== '' ? $userName : 'Admin' }}">
        @if($avatarSrc)
            <img src="{{ $avatarSrc }}" alt="{{ $userName !== '' ? $userName : 'Admin' }}">
        @else
            {{ $userInitial }}
        @endif
    </div>

    <form method="POST" action="{{ route('admin.logout') }}">
        @csrf

        <button class="a2-btn a2-btn-ghost" type="submit">
            تسجيل الخروج
        </button>
    </form>
</div>