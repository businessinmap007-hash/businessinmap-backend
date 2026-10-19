<?php

namespace App\Services\OwnerListings;

use App\Models\OwnerListing;
use App\Models\OwnerListingInquiry;
use App\Models\Thread;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcherService;
use App\Services\ThreadService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owner listings: ONE item per category per account, no business behind it. Posting, changing, finishing, renewing and
 * deleting a listing, who may see it, and the purge that removes it three days after it ends.
 */
final class OwnerListingService
{

    public function __construct(
        private readonly ThreadService $threads,
        private readonly NotificationDispatcherService $notifications
    ) {}

    /**
     * @param  array<string,mixed>  $data  {category, deal, title, description?, price?, price_unit?, governorate_id, city_id?, attributes?}
     */
    public function create(User $owner, array $data): OwnerListing
    {
        $category = (string) $data['category'];
        $deal = (string) $data['deal'];

        $this->assertDeal($category, $deal);

        $held = OwnerListing::query()->held()->where('user_id', $owner->id)->where('category', $category)->count();

        if ($held >= (int) config('bim.owner_listings.per_category', 1)) {
            throw ValidationException::withMessages(['category' => __('لديك إعلان في هذا التصنيف. أنهِه أو احذفه قبل أن تنشر غيره.')]);
        }

        [$price, $unit] = $this->priceOf($deal, $data);
        $this->assertPlace($data);

        return OwnerListing::create([
            'user_id' => (int) $owner->id,
            'category' => $category,
            'deal' => $deal,
            'title' => trim((string) $data['title']),
            'description' => isset($data['description']) ? (trim((string) $data['description']) ?: null) : null,
            'price' => $price,
            'price_unit' => $unit,
            'governorate_id' => (int) $data['governorate_id'],
            'city_id' => ! empty($data['city_id']) ? (int) $data['city_id'] : null,
            'attributes' => OwnerListingCategories::clean($category, $deal, (array) ($data['attributes'] ?? [])),
            'status' => OwnerListing::STATUS_ACTIVE,
            'expires_at' => now()->addDays((int) config('bim.owner_listings.active_days', 60)),
        ]);
    }

    /** @param array<string,mixed> $data */
    public function update(OwnerListing $listing, array $data): OwnerListing
    {
        $this->assertOpen($listing);

        $deal = (string) ($data['deal'] ?? $listing->deal);
        $this->assertDeal($listing->category, $deal);

        $merged = $data + ['price' => $listing->price, 'price_unit' => $listing->price_unit, 'governorate_id' => $listing->governorate_id, 'city_id' => $listing->city_id];
        [$price, $unit] = $this->priceOf($deal, $merged);
        $this->assertPlace($merged);

        $listing->update([
            'deal' => $deal,
            'title' => isset($data['title']) ? trim((string) $data['title']) : $listing->title,
            'description' => array_key_exists('description', $data) ? (trim((string) $data['description']) ?: null) : $listing->description,
            'price' => $price,
            'price_unit' => $unit,
            'governorate_id' => (int) $merged['governorate_id'],
            'city_id' => ! empty($merged['city_id']) ? (int) $merged['city_id'] : null,
            'attributes' => OwnerListingCategories::clean($listing->category, $deal, array_key_exists('attributes', $data) ? (array) $data['attributes'] : (array) $listing->attributes),
        ]);

        return $listing->refresh();
    }

    /** «تم البيع / تم التأجير»: hidden at once, removed three days later (its chats stay until then so the deal can be finished). */
    public function finish(OwnerListing $listing, string $reason): OwnerListing
    {
        if ($listing->status === OwnerListing::STATUS_DONE) {
            return $listing;
        }

        $allowed = $listing->deal === 'rent' ? ['rented', 'withdrawn'] : ($listing->deal === 'swap' ? ['swapped', 'withdrawn'] : ['sold', 'withdrawn']);

        if (! in_array($reason, $allowed, true)) {
            throw ValidationException::withMessages(['reason' => __('سبب غير صالح لهذا الإعلان.')]);
        }

        $listing->update([
            'status' => OwnerListing::STATUS_DONE,
            'done_reason' => $reason,
            'done_at' => now(),
            'purge_at' => now()->addDays((int) config('bim.owner_listings.purge_days', 3)),
        ]);

        $listing->inquiries()->get()->each(function (OwnerListingInquiry $inquiry) use ($listing) {
            $thread = Thread::query()->where('subject_type', $inquiry->getMorphClass())->where('subject_id', $inquiry->id)->first();

            if ($thread) {
                $this->threads->system($thread, 'أنهى صاحب الإعلان «' . $listing->title . '». تبقى هذه المحادثة ' . config('bim.owner_listings.purge_days', 3) . ' أيام ثم تُحذف.');
            }
        });

        return $listing->refresh();
    }

    /** Another stretch of days for a live (or just expired) listing; a finished one is not brought back. */
    public function renew(OwnerListing $listing): OwnerListing
    {
        if (! in_array($listing->status, [OwnerListing::STATUS_ACTIVE, OwnerListing::STATUS_EXPIRED], true)) {
            throw ValidationException::withMessages(['listing' => __('لا يمكن تجديد هذا الإعلان.')]);
        }

        $listing->update([
            'status' => OwnerListing::STATUS_ACTIVE,
            'expires_at' => now()->addDays((int) config('bim.owner_listings.active_days', 60)),
            'purge_at' => null,
        ]);

        return $listing->refresh();
    }

    /** The owner deletes it now: the listing, its photos and every chat about it. */
    public function remove(OwnerListing $listing): void
    {
        DB::transaction(function () use ($listing) {
            $this->purgeThreads($listing);
            $listing->delete();
        });
    }

    /**
     * The listings a person browses: live ones, newest first.
     *
     * @param  array<string,mixed>  $filters  {category?, deal?, governorate_id?, city_id?, q?, price_min?, price_max?}
     */
    public function browse(array $filters): Builder
    {
        return OwnerListing::query()->visible()
            ->when(! empty($filters['category']), fn (Builder $q) => $q->where('category', (string) $filters['category']))
            ->when(! empty($filters['deal']), fn (Builder $q) => $q->where('deal', (string) $filters['deal']))
            ->when(! empty($filters['governorate_id']), fn (Builder $q) => $q->where('governorate_id', (int) $filters['governorate_id']))
            ->when(! empty($filters['city_id']), fn (Builder $q) => $q->where('city_id', (int) $filters['city_id']))
            ->when(isset($filters['price_min']) && $filters['price_min'] !== '', fn (Builder $q) => $q->where('price', '>=', (float) $filters['price_min']))
            ->when(isset($filters['price_max']) && $filters['price_max'] !== '', fn (Builder $q) => $q->where('price', '<=', (float) $filters['price_max']))
            ->when(! empty($filters['q']), function (Builder $q) use ($filters) {
                $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], trim((string) $filters['q'])) . '%';
                $q->where(fn ($w) => $w->where('title', 'like', $term)->orWhere('description', 'like', $term));
            })
            ->latest('id');
    }

    /** A buyer opens (or returns to) his conversation about a listing. */
    public function inquire(OwnerListing $listing, User $buyer): OwnerListingInquiry
    {
        if ((int) $listing->user_id === (int) $buyer->id) {
            throw ValidationException::withMessages(['listing' => __('هذا إعلانك.')]);
        }

        if (! $listing->isVisible()) {
            throw ValidationException::withMessages(['listing' => __('هذا الإعلان لم يعد متاحًا.')]);
        }

        $existing = OwnerListingInquiry::query()->where('owner_listing_id', $listing->id)->where('buyer_id', $buyer->id)->first();

        if ($existing) {
            return $existing;
        }

        $inquiry = OwnerListingInquiry::create([
            'owner_listing_id' => (int) $listing->id,
            'buyer_id' => (int) $buyer->id,
            'seller_id' => (int) $listing->user_id,
            'status' => OwnerListingInquiry::STATUS_OPEN,
        ]);

        $this->notify('owner_listing_inquiry', (int) $listing->user_id, (int) $buyer->id, $inquiry, $listing->title);

        return $inquiry;
    }

    /**
     * The sweep, run on a schedule: a listing past its days becomes expired and is removed three days later; a finished or
     * expired one whose three days are over is deleted with its photos and its chats.
     *
     * @return array{expired:int,purged:int}
     */
    public function sweep(): array
    {
        $grace = (int) config('bim.owner_listings.purge_days', 3);

        $expired = OwnerListing::query()->whereIn('status', [OwnerListing::STATUS_ACTIVE, OwnerListing::STATUS_FLAGGED])
            ->whereNotNull('expires_at')->where('expires_at', '<=', now())->get();

        foreach ($expired as $listing) {
            $listing->update(['status' => OwnerListing::STATUS_EXPIRED, 'purge_at' => now()->addDays($grace)]);
        }

        $purged = 0;

        OwnerListing::query()->whereNotNull('purge_at')->where('purge_at', '<=', now())->get()->each(function (OwnerListing $listing) use (&$purged) {
            $this->remove($listing);
            $purged++;
        });

        return ['expired' => $expired->count(), 'purged' => $purged];
    }

    private function purgeThreads(OwnerListing $listing): void
    {
        $inquiry = new OwnerListingInquiry();

        $ids = $listing->inquiries()->pluck('id');

        Thread::query()->where('subject_type', $inquiry->getMorphClass())->whereIn('subject_id', $ids)->get()
            ->each(fn (Thread $thread) => $this->threads->purge($thread));
    }

    private function assertOpen(OwnerListing $listing): void
    {
        if (! in_array($listing->status, [OwnerListing::STATUS_ACTIVE, OwnerListing::STATUS_FLAGGED], true)) {
            throw ValidationException::withMessages(['listing' => __('لا يمكن تعديل إعلان منتهٍ.')]);
        }
    }

    private function assertDeal(string $category, string $deal): void
    {
        if (! OwnerListingCategories::exists($category)) {
            throw ValidationException::withMessages(['category' => __('تصنيف غير معروف.')]);
        }

        if (! in_array($deal, OwnerListingCategories::deals($category), true)) {
            throw ValidationException::withMessages(['deal' => __('هذا التصنيف لا يقبل هذا النوع من الإعلان.')]);
        }
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array{0:?float,1:?string} [price, per what] — a sale or a rent needs a price, a rent needs the period
     */
    private function priceOf(string $deal, array $data): array
    {
        $price = isset($data['price']) && $data['price'] !== '' ? round((float) $data['price'], 2) : null;

        if ($deal !== 'swap' && ($price === null || $price < 1)) {
            throw ValidationException::withMessages(['price' => __('اكتب السعر.')]);
        }

        if ($price !== null && $price > 1000000000) {
            throw ValidationException::withMessages(['price' => __('اكتب سعرًا صالحًا.')]);
        }

        $unit = $deal === 'rent' ? (string) ($data['price_unit'] ?? '') : null;

        if ($deal === 'rent' && ! in_array($unit, ['day', 'week', 'month'], true)) {
            throw ValidationException::withMessages(['price_unit' => __('اختر مدة الإيجار: يوم أو أسبوع أو شهر.')]);
        }

        return [$price, $unit];
    }

    /** @param array<string,mixed> $data */
    private function assertPlace(array $data): void
    {
        if (empty($data['governorate_id']) || ! DB::table('governorates')->where('id', (int) $data['governorate_id'])->exists()) {
            throw ValidationException::withMessages(['governorate_id' => __('اختر المحافظة.')]);
        }

        if (! empty($data['city_id']) && (int) DB::table('cities')->where('id', (int) $data['city_id'])->value('governorate_id') !== (int) $data['governorate_id']) {
            throw ValidationException::withMessages(['city_id' => __('المدينة ليست من هذه المحافظة.')]);
        }
    }

    private function notify(string $eventKey, int $recipientId, int $actorId, OwnerListingInquiry $inquiry, string $body): void
    {
        try {
            $this->notifications->dispatch($eventKey, $recipientId, [
                'actor_id' => $actorId,
                'notifiable_type' => OwnerListingInquiry::class,
                'notifiable_id' => (int) $inquiry->id,
                'source_id' => (int) $inquiry->id,
                'service_type' => 'booking',
                'action_type' => 'open_owner_listing_chat',
                'action_url' => '/owner-listing-inquiries/' . $inquiry->id,
                'body_ar' => $body,
                'body_en' => $body,
                'meta' => ['inquiry_id' => (int) $inquiry->id, 'title' => $body],
            ]);
        } catch (\Throwable $e) {
            // best-effort, like every notification of the platform
        }
    }
}
