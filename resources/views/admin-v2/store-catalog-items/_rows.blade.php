@forelse($rows as $row)
    <tr>
        <td class="a2-fw-900">{{ $row->id }}</td>
        <td>
            <div class="a2-fw-900">{{ $row->business_name ?: ('#'.$row->business_id) }}</div>
            <div class="a2-muted a2-mt-8">ID: {{ $row->business_id }}</div>
        </td>
        <td>
            <div class="a2-fw-900">{{ $row->product_name_ar ?: $row->product_name_en }}</div>
            <div class="a2-muted a2-mt-8" dir="ltr">{{ $row->bim_code }}</div>
            @if($row->package_label_ar || $row->package_label_en)
                <div class="a2-muted a2-mt-8">{{ $row->package_label_ar ?: $row->package_label_en }}</div>
            @endif
        </td>
        <td>{{ $row->child_name_ar ?: ($row->child_name_en ?: '—') }}</td>
        <td>{{ $row->brand_name_ar ?: ($row->brand_name_en ?: '—') }}</td>
        <td>
            <div class="a2-fw-900">{{ number_format((float)$row->price, 2) }} {{ $row->currency_code ?: 'EGP' }}</div>
            @if($row->offer_price !== null)
                <div class="a2-pill a2-pill-success a2-mt-8">عرض: {{ number_format((float)$row->offer_price, 2) }}</div>
            @endif
        </td>
        <td>
            <div>{{ number_format((float)$row->stock_quantity, 3) }}</div>
            <div class="a2-muted a2-mt-8">{{ $row->stock_status }}</div>
        </td>
        <td>
            @if((int)$row->is_available === 1 && $row->status === 'active')
                <span class="a2-pill a2-pill-success">متاح</span>
            @else
                <span class="a2-pill a2-pill-danger">غير متاح</span>
            @endif
        </td>
        <td>
            <form method="POST" action="{{ route('admin.store-catalog-items.destroy', $row->id) }}" onsubmit="return confirm('حذف المنتج من المتجر؟')">
                @csrf
                @method('DELETE')
                <button class="a2-btn a2-btn-danger" type="submit">حذف</button>
            </form>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="9" class="a2-empty">لا توجد منتجات مرتبطة بمتاجر حتى الآن.</td>
    </tr>
@endforelse
