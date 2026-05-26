@php
    /*
    |--------------------------------------------------------------------------
    | Auto Results Bar
    |--------------------------------------------------------------------------
    | يظهر تلقائيًا لو الصفحة تحتوي Paginator.
    |
    | يمكن تعطيله من أي view بإضافة:
    | @php($disableResultsbarAuto = true)
    |
    | ويمكن تحديد paginator معين بإضافة:
    | @php($resultsbarPaginator = $rows)
    |--------------------------------------------------------------------------
    */

    $disabled = (bool)($disableResultsbarAuto ?? false);

    $p = null;

    if (! $disabled) {
        if (
            isset($resultsbarPaginator) &&
            (
                $resultsbarPaginator instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator ||
                $resultsbarPaginator instanceof \Illuminate\Contracts\Pagination\Paginator
            )
        ) {
            $p = $resultsbarPaginator;
        } else {
            foreach (get_defined_vars() as $name => $v) {
                if (in_array($name, [
                    '__data',
                    '__path',
                    'app',
                    'errors',
                    'disableResultsbarAuto',
                    'resultsbarPaginator',
                ], true)) {
                    continue;
                }

                if (
                    $v instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator ||
                    $v instanceof \Illuminate\Contracts\Pagination\Paginator
                ) {
                    $p = $v;
                    break;
                }
            }
        }
    }

    $isLen = $p instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator;
    $hasAny = ($p && $p instanceof \Countable) ? ($p->count() > 0) : false;

    $cur = ($p && method_exists($p, 'currentPage')) ? (int) $p->currentPage() : 1;
    $lastP = ($isLen && method_exists($p, 'lastPage')) ? (int) $p->lastPage() : 1;

    $prevUrl = ($p && method_exists($p, 'previousPageUrl')) ? $p->previousPageUrl() : null;
    $nextUrl = ($p && method_exists($p, 'nextPageUrl')) ? $p->nextPageUrl() : null;

    $onFirst = ($p && method_exists($p, 'onFirstPage')) ? (bool) $p->onFirstPage() : ($cur <= 1);
    $hasMore = ($p && method_exists($p, 'hasMorePages')) ? (bool) $p->hasMorePages() : ($cur < $lastP);

    $hasPages = $p && method_exists($p, 'hasPages') && $p->hasPages();
@endphp

@if(! $disabled && $p && $hasPages && $hasAny)
    <div class="a2-resultsbar a2-resultsbar--bottom" role="navigation" aria-label="Results pagination">
        <div class="a2-resultsbar-links">
            @if($onFirst || ! $prevUrl)
                <span class="a2-resultsbar-btn is-disabled" aria-disabled="true">Previous</span>
            @else
                <a class="a2-resultsbar-btn" href="{{ $prevUrl }}" rel="prev">Previous</a>
            @endif

            @if($hasMore && $nextUrl)
                <a class="a2-resultsbar-btn" href="{{ $nextUrl }}" rel="next">Next</a>
            @else
                <span class="a2-resultsbar-btn is-disabled" aria-disabled="true">Next</span>
            @endif
        </div>

        <div class="a2-resultsbar-meta">
            @if($isLen)
                Showing <b>{{ (int) ($p->firstItem() ?? 0) }}</b>
                to <b>{{ (int) ($p->lastItem() ?? 0) }}</b>
                of <b>{{ (int) ($p->total() ?? 0) }}</b> results
                <span class="a2-resultsbar-sep">•</span>
                Page <b>{{ $cur }}</b> / <b>{{ max(1, $lastP) }}</b>
            @else
                Page <b>{{ $cur }}</b>
            @endif
        </div>
    </div>
@endif