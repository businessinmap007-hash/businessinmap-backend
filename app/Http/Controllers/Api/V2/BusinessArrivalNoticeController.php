<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Http\Controllers\Controller;
use App\Models\BusinessBookingSetting;
use App\Services\ArrivalNotice;
use Illuminate\Http\Request;

/**
 * «يجب التواجد قبل الموعد بـ ١٥ دقيقة» — the business's own notice on every booking and appointment it takes.
 * Minutes and a sentence of its own; either or both, or neither (the notice is then gone).
 */
final class BusinessArrivalNoticeController extends Controller
{
    use ResolvesOwnerCatalog;

    /** GET /api/v2/business/booking-settings/arrival-notice */
    public function show()
    {
        return response()->json(['success' => true, 'data' => $this->payload($this->businessId())]);
    }

    /** PUT /api/v2/business/booking-settings/arrival-notice */
    public function update(Request $request)
    {
        $data = $request->validate([
            'minutes' => ['nullable', 'integer', 'min:0', 'max:' . ArrivalNotice::MAX_MINUTES],
            'text' => ['nullable', 'string', 'max:255'],
        ]);

        $minutes = isset($data['minutes']) && (int) $data['minutes'] > 0 ? (int) $data['minutes'] : null;
        $text = isset($data['text']) && trim((string) $data['text']) !== '' ? trim((string) $data['text']) : null;

        BusinessBookingSetting::query()->updateOrCreate(
            ['business_id' => $this->businessId()],
            ['arrival_notice_minutes' => $minutes, 'arrival_notice_text' => $text]
        );

        return response()->json(['success' => true, 'data' => $this->payload($this->businessId())]);
    }

    /** @return array{minutes:?int,text:?string,message:?string} */
    private function payload(int $businessId): array
    {
        $notice = ArrivalNotice::forBusiness($businessId);

        return [
            'minutes' => $notice['minutes'] ?? null,
            'text' => $notice['text'] ?? null,
            'message' => $notice['message'] ?? null,
        ];
    }
}
