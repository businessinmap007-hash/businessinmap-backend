<?php

namespace App\Services\Partnerships;

use App\Models\BusinessPartnership;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcherService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The partnership core — a contract between TWO businesses, whatever their trades (a hotel and a travel agency, a factory and a
 * trading company, a supplier and a shop).
 *
 * One rule runs through everything built on it: **only the two parties may read a partnership or anything agreed inside it.** A
 * third business, a customer, a guest — all get a 404, never a 403 (a 403 would confirm the pairing exists). What the public
 * sees of a contract is only its RESULT (a room offered at a price), never the terms, the contract price or the quantities.
 *
 * The party that owns the asset (the hotel, the factory) invites; the other accepts. Contract kinds plug in beside it — the first
 * is the allocation of bookable inventory (AllocationService), more (supply price lists, agreed quotas) can follow without
 * touching this class.
 */
final class PartnershipService
{
    public function __construct(private readonly NotificationDispatcherService $notifications)
    {
    }

    /** Every partnership the business is a party to, as owner or as partner. @return Collection<int,BusinessPartnership> */
    public function mine(int $businessId): Collection
    {
        return BusinessPartnership::query()
            ->where(fn ($q) => $q->where('owner_business_id', $businessId)->orWhere('partner_business_id', $businessId))
            ->with(['ownerBusiness:id,name,logo,category_child_id', 'partnerBusiness:id,name,logo,category_child_id'])
            ->orderByDesc('id')
            ->get();
    }

    /** The partnership, only for one of its two parties — a stranger gets 404 whether or not it exists. */
    public function forParty(int $id, int $businessId): BusinessPartnership
    {
        return BusinessPartnership::query()
            ->whereKey($id)
            ->where(fn ($q) => $q->where('owner_business_id', $businessId)->orWhere('partner_business_id', $businessId))
            ->with(['ownerBusiness:id,name,logo', 'partnerBusiness:id,name,logo'])
            ->firstOrFail();
    }

    public function isOwner(BusinessPartnership $p, int $businessId): bool
    {
        return (int) $p->owner_business_id === $businessId;
    }

    /** The owner invites another business. @param array{type?:string,terms?:array<string,mixed>} $data */
    public function invite(User $owner, int $partnerId, array $data = []): BusinessPartnership
    {
        $partner = User::query()->where('type', User::TYPE_BUSINESS)->find($partnerId);

        if (! $partner || (int) $partner->id === (int) $owner->id) {
            throw ValidationException::withMessages(['partner_business_id' => __('اختر نشاطًا آخر.')]);
        }

        $type = (string) ($data['type'] ?? BusinessPartnership::TYPE_AGENCY);

        $open = BusinessPartnership::query()
            ->where('owner_business_id', $owner->id)->where('partner_business_id', $partner->id)
            ->whereIn('status', [BusinessPartnership::STATUS_PENDING, BusinessPartnership::STATUS_ACTIVE, BusinessPartnership::STATUS_PAUSED])
            ->exists();

        if ($open) {
            throw ValidationException::withMessages(['partner_business_id' => __('بينكما شراكة قائمة أو دعوة معلّقة.')]);
        }

        $p = BusinessPartnership::create([
            'owner_business_id' => (int) $owner->id,
            'partner_business_id' => (int) $partner->id,
            'relationship_type' => $type,
            'status' => BusinessPartnership::STATUS_PENDING,
            'approval_required' => true,
            'created_by' => (int) $owner->id,
            'terms' => $data['terms'] ?? null,
        ]);

        $this->tell((int) $partner->id, $p, 'دعوة شراكة', 'A partnership invitation',
            $owner->name . ' يدعوك إلى شراكة. تفاصيلها لا يراها غيركما.', $owner->name . ' invites you to a partnership. Nobody but the two of you sees its details.');

        return $p->fresh(['ownerBusiness:id,name,logo', 'partnerBusiness:id,name,logo']);
    }

    /** The invited business answers. */
    public function respond(BusinessPartnership $p, int $businessId, bool $accept): BusinessPartnership
    {
        if ((int) $p->partner_business_id !== $businessId || $p->status !== BusinessPartnership::STATUS_PENDING) {
            throw ValidationException::withMessages(['partnership' => __('لا توجد دعوة تنتظر ردك.')]);
        }

        $p->forceFill([
            'status' => $accept ? BusinessPartnership::STATUS_ACTIVE : BusinessPartnership::STATUS_REJECTED,
            'approved_by' => $accept ? $businessId : null,
            'approved_at' => $accept ? now() : null,
        ])->save();

        $this->tell((int) $p->owner_business_id, $p, $accept ? 'قُبلت دعوة الشراكة' : 'رُفضت دعوة الشراكة',
            $accept ? 'The invitation was accepted' : 'The invitation was declined',
            $accept ? 'قبل الطرف الآخر دعوة الشراكة.' : 'رفض الطرف الآخر دعوة الشراكة.',
            $accept ? 'The other side accepted your partnership invitation.' : 'The other side declined your partnership invitation.');

        return $p->fresh(['ownerBusiness:id,name,logo', 'partnerBusiness:id,name,logo']);
    }

    /** Either party pauses, resumes or ends it. Ending is final. */
    public function change(BusinessPartnership $p, string $action): BusinessPartnership
    {
        $next = match ($action) {
            'pause' => $p->status === BusinessPartnership::STATUS_ACTIVE ? BusinessPartnership::STATUS_PAUSED : null,
            'resume' => $p->status === BusinessPartnership::STATUS_PAUSED ? BusinessPartnership::STATUS_ACTIVE : null,
            'end' => in_array($p->status, [BusinessPartnership::STATUS_ACTIVE, BusinessPartnership::STATUS_PAUSED, BusinessPartnership::STATUS_PENDING], true) ? BusinessPartnership::STATUS_CANCELLED : null,
            default => null,
        };

        if ($next === null) {
            throw ValidationException::withMessages(['partnership' => __('لا يمكن تنفيذ هذا الإجراء في حالة الشراكة الحالية.')]);
        }

        $p->update(['status' => $next]);

        // what was granted inside it stops with it
        if ($next !== BusinessPartnership::STATUS_ACTIVE) {
            \App\Models\BookableAllocation::query()->where('partnership_id', $p->id)->where('status', \App\Models\BookableAllocation::STATUS_ACTIVE)
                ->update(['status' => $next === BusinessPartnership::STATUS_PAUSED ? \App\Models\BookableAllocation::STATUS_PAUSED : \App\Models\BookableAllocation::STATUS_STOPPED]);
        }

        return $p->fresh(['ownerBusiness:id,name,logo', 'partnerBusiness:id,name,logo']);
    }

    /**
     * What one party sees: the other side, the kind, the state and the terms. Contract kinds add their own blocks.
     *
     * @return array<string,mixed>
     */
    public function present(BusinessPartnership $p, int $viewerId): array
    {
        $isOwner = $this->isOwner($p, $viewerId);
        $other = $isOwner ? $p->partnerBusiness : $p->ownerBusiness;

        return [
            'id' => (int) $p->id,
            'role' => $isOwner ? 'owner' : 'partner',
            'status' => (string) $p->status,
            'relationship_type' => (string) $p->relationship_type,
            'other' => $other ? ['id' => (int) $other->id, 'name' => (string) $other->name, 'logo' => $other->logo] : null,
            'terms' => $p->terms,
            'starts_at' => optional($p->starts_at)->toIso8601String(),
            'ends_at' => optional($p->ends_at)->toIso8601String(),
            'created_at' => optional($p->created_at)->toIso8601String(),
            'awaiting_my_answer' => ! $isOwner && $p->status === BusinessPartnership::STATUS_PENDING,
        ];
    }

    private function tell(int $userId, BusinessPartnership $p, string $titleAr, string $titleEn, string $bodyAr, string $bodyEn): void
    {
        try {
            $this->notifications->dispatch('project_stage_completed', $userId, [
                'title_ar' => $titleAr, 'title_en' => $titleEn, 'body_ar' => $bodyAr, 'body_en' => $bodyEn,
                'notifiable_type' => BusinessPartnership::class, 'notifiable_id' => (int) $p->id,
                'source_type' => BusinessPartnership::class, 'source_id' => (int) $p->id,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
