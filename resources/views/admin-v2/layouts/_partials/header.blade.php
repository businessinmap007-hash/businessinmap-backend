{{-- 
    Admin V2 Header Partial

    ملاحظة:
    الـ Topbar الأساسي موجود داخل:
    resources/views/admin-v2/layouts/master.blade.php

    هذا الملف متروك كـ backward-compatible partial فقط
    لأي صفحات قديمة قد تستدعي header مباشرة.
--}}

<div class="a2-header-partial">
    @includeIf('admin-v2.layouts._partials.userbar')
</div>