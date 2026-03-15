@extends('admin-v2.layouts.master')

@section('title', 'الألبومات')
@section('body_class', 'admin-v2-albums')

@section('content')
@php
    $qVal = (string) ($q ?? '');
    $userIdVal = (string) ($user_id ?? '');
    $categoryIdVal = (string) ($category_id ?? '');
    $perPageVal = (int) ($perPage ?? 50);
    $sortNow = (string) ($sort ?? 'id');
    $dirNow = (string) ($dir ?? 'desc');

    $perPageOptions = [10, 20, 50, 100];

    $sortOptions = [
        'id' => 'ID',
        'created_at' => 'Created',
        'updated_at' => 'Updated',
        'user_id' => 'User ID',
        'title_ar' => 'Title AR',
        'title_en' => 'Title EN',
    ];
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">الألبومات</h1>
            <div class="a2-page-subtitle">إدارة الألبومات (عنوان + وصف + غلاف)</div>
        </div>

        <div class="a2-page-actions">
            <a class="a2-btn a2-btn-primary" href="{{ route('admin.albums.create') }}">
                إضافة ألبوم
            </a>
        </div>
    </div>

    <div class="a2-card">
        <form method="GET" action="{{ route('admin.albums.index') }}" class="a2-filterbar">
            <input
                class="a2-input a2-filter-search"
                name="q"
                value="{{ $qVal }}"
                placeholder="بحث: ID / عنوان / وصف / اسم المستخدم / email / phone"
            >

            <select class="a2-select a2-filter-md" name="category_id">
                <option value="">كل الأقسام الرئيسية</option>
                @foreach(($categoriesForFilter ?? []) as $c)
                    @php
                        $nm = (string) ($c->name_ar ?? '');
                    @endphp
                    <option value="{{ $c->id }}" @selected((string) $c->id === $categoryIdVal)>
                        {{ $nm !== '' ? $nm : ('#' . $c->id) }}
                    </option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-sm" name="sort">
                @foreach($sortOptions as $k => $label)
                    <option value="{{ $k }}" @selected($sortNow === $k)>{{ $label }}</option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-sm" name="dir">
                <option value="desc" @selected($dirNow === 'desc')>DESC</option>
                <option value="asc" @selected($dirNow === 'asc')>ASC</option>
            </select>

            <select class="a2-select a2-filter-sm" name="per_page">
                @foreach($perPageOptions as $n)
                    <option value="{{ $n }}" @selected((int) $perPageVal === (int) $n)>
                        {{ $n }} / صفحة
                    </option>
                @endforeach
            </select>

            <div class="a2-filter-actions">
                <button type="submit" class="a2-btn a2-btn-primary">تطبيق</button>
                <a class="a2-btn a2-btn-ghost" href="{{ route('admin.albums.index') }}">تفريغ</a>
            </div>
        </form>

        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cover</th>
                        <th>Title</th>
                        <th>User</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($items as $row)
                        @php
                            $showUrl = route('admin.albums.show', $row->id);

                            $titleAr = (string) ($row->title_ar ?? '');
                            $titleEn = (string) ($row->title_en ?? '');
                            $title = $titleAr !== '' ? $titleAr : ($titleEn !== '' ? $titleEn : ('#' . $row->id));

                            $userName = (string) ($row->user->name ?? '');
                            $userLabel = $userName !== '' ? $userName : ($row->user_id ? '#' . $row->user_id : '—');

                            $userShowUrl = null;
                            if ($row->user_id) {
                                try {
                                    $userShowUrl = route('admin.users.show', $row->user_id);
                                } catch (\Throwable $e) {
                                    $userShowUrl = null;
                                }
                            }

                            $imgPath = (string) ($row->image ?? '');
                        @endphp

                        <tr>
                            <td>
                                <a class="a2-link" href="{{ $showUrl }}">{{ $row->id }}</a>
                            </td>

                            <td>
                                @if($imgPath !== '')
                                    <x-admin-v2.image :path="$imgPath" size="52" radius="12px" />
                                @else
                                    <div class="a2-album-cover-empty">
                                        —
                                    </div>
                                @endif
                            </td>

                            <td>
                                <a class="a2-link a2-clip" href="{{ $showUrl }}" title="{{ $title }}">
                                    {{ $title }}
                                </a>
                            </td>

                            <td>
                                @if($userShowUrl)
                                    <a class="a2-link a2-clip" href="{{ $userShowUrl }}" title="{{ $userLabel }}">
                                        {{ $userLabel }}
                                    </a>
                                @else
                                    <span class="a2-clip" title="{{ $userLabel }}">
                                        {{ $userLabel }}
                                    </span>
                                @endif
                            </td>

                            <td dir="ltr">
                                {{ $row->created_at ? $row->created_at->format('Y-m-d H:i') : '—' }}
                            </td>

                            <td>
                                <div class="a2-actions">
                                    <a class="a2-btn a2-btn-ghost a2-btn-sm" href="{{ route('admin.albums.edit', $row->id) }}">
                                        تعديل
                                    </a>

                                    <form method="POST" action="{{ route('admin.albums.destroy', $row->id) }}" onsubmit="return confirm('حذف الألبوم؟');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="a2-btn a2-btn-ghost a2-btn-sm" type="submit">
                                            حذف
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="a2-empty-cell">لا يوجد بيانات</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(method_exists($items, 'links'))
            <div class="a2-paginate">
                {{ $items->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
