<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Http\Controllers\Controller;
use App\Models\BookableItem;
use App\Models\BookableItemBlockedSlot;
use App\Models\BookableItemPriceRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * إغلاقُ وحدةٍ، وتسعيرُ يومٍ بعينه — من الباب الذى يعرف.
 *
 * الجدولان مبنيّان منذ زمن: `bookable_item_blocked_slots` تقرؤها
 * `BookableAvailabilityService` فترفض حجزًا يقع فى إغلاق، و
 * `bookable_item_price_rules` لها نموذجُها وخدمتُها وشاشةُ تقويمٍ كاملة فى
 * لوحة الإدارة. وكلاهما بصفرِ صفوف.
 *
 * والسببُ ليس أن أحدًا لا يحتاجهما، بل أن البابَ الوحيد كان فى لوحة الإدارة:
 * موظّفُ المنصّة لا يعرف أن غرفة ١٠٣ تحت الصيانة الأسبوع القادم، ولا أن
 * الجمعة والسبت أغلى فى هذا الفندق. الذى يعرف هو صاحبُ المحل، ولم يكن له
 * شاشة.
 *
 * فهما هنا على شاشة الوحدة نفسها: كلُّ ما يخصّ غرفةً فى مكانٍ واحد.
 */
class BookableItemCalendarController extends Controller
{
    use ResolvesOwnerCatalog;

    private function scopedItem(int $id): BookableItem
    {
        return BookableItem::query()
            ->where('business_id', $this->businessId())
            ->findOrFail($id);
    }

    /*
    |--------------------------------------------------------------------------
    | الإغلاق
    |--------------------------------------------------------------------------
    */

    public function storeBlockedSlot(Request $request, int $id): RedirectResponse
    {
        $item = $this->scopedItem($id);

        $data = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'block_type' => ['required', Rule::in([
                BookableItemBlockedSlot::TYPE_MANUAL,
                BookableItemBlockedSlot::TYPE_MAINTENANCE,
                BookableItemBlockedSlot::TYPE_HOLIDAY,
            ])],
            'reason' => ['nullable', 'string', 'max:255'],
        ], [], [
            'starts_at' => 'من',
            'ends_at' => 'إلى',
            'block_type' => 'السبب',
        ]);

        /*
         * `business_id` و`platform_service_id` يُنسخان من الوحدة لا من الطلب.
         *
         * الخدمةُ صفةُ الوحدة، وإرسالُها من الشاشة يفتح بابًا لإغلاقٍ يُنسب
         * إلى خدمةٍ لا تبيعها هذه الوحدة أصلًا. و`created_by` يقول من أغلق:
         * صاحبُ المحل أو موظّفٌ مفوَّض، وهما ليسا نفس الشخص.
         */
        $item->blockedSlots()->create([
            'business_id' => (int) $item->business_id,
            'platform_service_id' => (int) $item->service_id,
            'block_type' => $data['block_type'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'reason' => trim((string) ($data['reason'] ?? '')) ?: null,
            'created_by' => (int) auth()->id(),
            'is_active' => 1,
        ]);

        return back()->with('success', __('تم إغلاق الوحدة في هذه الفترة.'));
    }

    public function destroyBlockedSlot(int $id, int $slot): RedirectResponse
    {
        $this->scopedItem($id)->blockedSlots()->findOrFail($slot)->delete();

        return back()->with('success', __('تم فتح الفترة من جديد.'));
    }

    /*
    |--------------------------------------------------------------------------
    | قواعد السعر
    |--------------------------------------------------------------------------
    */

    public function storePriceRule(Request $request, int $id): RedirectResponse
    {
        $item = $this->scopedItem($id);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:150'],
            'rule_type' => ['required', Rule::in([
                BookableItemPriceRule::RULE_DATE_RANGE,
                BookableItemPriceRule::RULE_WEEKDAY,
                BookableItemPriceRule::RULE_SEASON,
                BookableItemPriceRule::RULE_SPECIAL_DAY,
            ])],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'weekday' => ['nullable', 'integer', 'between:0,6'],
            'price_type' => ['required', Rule::in([
                BookableItemPriceRule::PRICE_FIXED,
                BookableItemPriceRule::PRICE_DELTA,
                BookableItemPriceRule::PRICE_PERCENT,
            ])],
            'price_value' => ['required', 'numeric'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:999'],
        ], [], [
            'rule_type' => 'نوع القاعدة',
            'price_type' => 'طريقة التسعير',
            'price_value' => 'القيمة',
        ]);

        /*
         * قاعدةٌ لا تقول متى تنطبق تنطبق دائمًا.
         *
         * `scopeForDate` تقبل السطرَ بلا تاريخين على أنه «كلَّ يوم»، فقاعدةُ
         * مدًى بتاريخٍ واحدٍ فارغ تصير قاعدةً دائمة بلا أن يقصد أحد — وتغلب
         * السعرَ الأساسىَّ إلى الأبد. تُرفض هنا بدل أن تُكتشف فى فاتورة.
         */
        if ($data['rule_type'] === BookableItemPriceRule::RULE_WEEKDAY) {
            if (($data['weekday'] ?? null) === null) {
                return back()->withInput()->withErrors(['weekday' => __('اختر اليوم الذي تنطبق عليه القاعدة.')]);
            }
        } elseif (empty($data['start_date']) || empty($data['end_date'])) {
            return back()->withInput()->withErrors(['start_date' => __('حدّد بداية الفترة ونهايتها.')]);
        }

        $item->priceRules()->create([
            'business_id' => (int) $item->business_id,
            'platform_service_id' => (int) $item->service_id,
            'title' => trim((string) ($data['title'] ?? '')) ?: null,
            'rule_type' => $data['rule_type'],
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'weekday' => $data['weekday'] ?? null,
            'price_type' => $data['price_type'],
            'price_value' => (float) $data['price_value'],
            'currency' => 'EGP',
            // الأصغرُ أسبق: من أراد قاعدةً تغلب غيرَها أعطاها رقمًا أقل.
            'priority' => (int) ($data['priority'] ?? 100),
            'created_by' => (int) auth()->id(),
            'is_active' => 1,
        ]);

        return back()->with('success', __('تمت إضافة قاعدة السعر.'));
    }

    public function destroyPriceRule(int $id, int $rule): RedirectResponse
    {
        $this->scopedItem($id)->priceRules()->findOrFail($rule)->delete();

        return back()->with('success', __('تم حذف قاعدة السعر.'));
    }
}
