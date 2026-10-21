<?php

namespace App\Services\TripPrograms;

use App\Models\BookableAllocation;
use App\Models\BusinessPartnership;
use App\Models\TripProgram;
use App\Models\TripProgramDay;
use App\Models\TripProgramItem;
use App\Services\Media\ImageUploadService;
use App\Services\Partnerships\AllocationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * «برنامج الرحلة»: a tour company writes a trip as days and items (a piece of text, a photo, the card of a room a hotel agreed to give
 * it) and publishes it on its page.
 *
 * A room item points at the ALLOCATION. What a customer reads out of it is the room card (photos, what it carries) and the price THE
 * COMPANY set — the contract price and the quantities of the allocation are the two parties' alone and are never in this payload.
 * When the hotel stops the allocation, or the partnership ends, the card disappears and the item keeps its words.
 */
final class TripProgramService
{
    public const MAX_ITEMS_PER_DAY = 30;
    public const MAX_DAYS = 31;

    public function __construct(private readonly AllocationService $allocations)
    {
    }

    /** @return Collection<int,TripProgram> */
    public function mine(int $businessId): Collection
    {
        return TripProgram::query()->where('business_id', $businessId)->withCount('days')->orderByDesc('id')->get();
    }

    public function ownedOrFail(int $businessId, int $id): TripProgram
    {
        return TripProgram::query()->where('business_id', $businessId)->findOrFail($id);
    }

    public function publishedOrFail(int $businessId, int $id): TripProgram
    {
        return TripProgram::query()->published()->where('business_id', $businessId)->findOrFail($id);
    }

    /** @param array{title:string,summary?:?string} $data */
    public function create(int $businessId, array $data): TripProgram
    {
        return TripProgram::create(['business_id' => $businessId, 'title' => $data['title'], 'summary' => $data['summary'] ?? null, 'status' => TripProgram::STATUS_DRAFT]);
    }

    /** @param array{title?:string,summary?:?string} $data */
    public function update(TripProgram $program, array $data): TripProgram
    {
        $program->update(array_intersect_key($data, array_flip(['title', 'summary'])));

        return $program;
    }

    public function delete(TripProgram $program): void
    {
        $program->delete();
    }

    public function setPublished(TripProgram $program, bool $published): TripProgram
    {
        if ($published && ! TripProgramItem::query()->whereIn('day_id', $program->days()->pluck('id'))->exists()) {
            throw ValidationException::withMessages(['program' => __('أضف يومًا وبندًا واحدًا على الأقل قبل النشر.')]);
        }

        $program->update(['status' => $published ? TripProgram::STATUS_PUBLISHED : TripProgram::STATUS_DRAFT, 'published_at' => $published ? now() : null]);

        return $program;
    }

    // ---- days ----------------------------------------------------------------------------------------------------------

    public function addDay(TripProgram $program, ?string $title): TripProgramDay
    {
        if ($program->days()->count() >= self::MAX_DAYS) {
            throw ValidationException::withMessages(['title' => __('الحد الأقصى :n يومًا.', ['n' => self::MAX_DAYS])]);
        }

        return $program->days()->create(['position' => 1 + (int) $program->days()->max('position'), 'title' => $title]);
    }

    public function dayOrFail(TripProgram $program, int $dayId): TripProgramDay
    {
        return $program->days()->findOrFail($dayId);
    }

    public function removeDay(TripProgramDay $day): void
    {
        $program = $day->program;
        $day->delete();
        $this->renumber($program->days()->get());
    }

    public function moveDay(TripProgramDay $day, int $direction): void
    {
        $this->swap($day->program->days()->get(), $day, $direction);
    }

    // ---- items ---------------------------------------------------------------------------------------------------------

    /** @param array{kind:string,body?:?string,allocation_id?:?int} $data */
    public function addItem(TripProgram $program, TripProgramDay $day, array $data): TripProgramItem
    {
        if ($day->items()->count() >= self::MAX_ITEMS_PER_DAY) {
            throw ValidationException::withMessages(['kind' => __('الحد الأقصى :n بندًا لليوم.', ['n' => self::MAX_ITEMS_PER_DAY])]);
        }

        $kind = (string) $data['kind'];
        $allocationId = null;

        if ($kind === TripProgramItem::KIND_ROOM) {
            $allocationId = (int) ($data['allocation_id'] ?? 0);

            if (! $this->cardsQuery((int) $program->business_id)->whereKey($allocationId)->exists()) {
                throw ValidationException::withMessages(['allocation_id' => __('هذه الغرفة ليست من الغرف الممنوحة لشركتك.')]);
            }
        } elseif ($kind === TripProgramItem::KIND_TEXT && trim((string) ($data['body'] ?? '')) === '') {
            throw ValidationException::withMessages(['body' => __('اكتب نص البند.')]);
        }

        return $day->items()->create([
            'position' => 1 + (int) $day->items()->max('position'),
            'kind' => $kind,
            'body' => $data['body'] ?? null,
            'allocation_id' => $allocationId,
        ]);
    }

    public function itemOrFail(TripProgram $program, int $itemId): TripProgramItem
    {
        return TripProgramItem::query()->whereIn('day_id', $program->days()->pluck('id'))->findOrFail($itemId);
    }

    public function updateItem(TripProgramItem $item, ?string $body): TripProgramItem
    {
        if ($item->kind === TripProgramItem::KIND_TEXT && trim((string) $body) === '') {
            throw ValidationException::withMessages(['body' => __('اكتب نص البند.')]);
        }

        $item->update(['body' => $body]);

        return $item;
    }

    public function removeItem(TripProgramItem $item): void
    {
        $day = $item->day;
        $item->delete();
        $this->renumber($day->items()->get());
    }

    public function moveItem(TripProgramItem $item, int $direction): void
    {
        $this->swap($item->day->items()->get(), $item, $direction);
    }

    /** The photo of an image item (one per item; a new one replaces the old). */
    public function setImage(TripProgramItem $item, UploadedFile $file): TripProgramItem
    {
        if ($item->kind !== TripProgramItem::KIND_IMAGE) {
            throw ValidationException::withMessages(['image' => __('هذا البند ليس صورة.')]);
        }

        $uploads = app(ImageUploadService::class);

        foreach ($item->images()->get() as $old) {
            $uploads->delete($old->image);
            $old->delete();
        }

        $item->images()->create(['image' => $uploads->store($file)]);

        return $item->load('images');
    }

    // ---- rooms ---------------------------------------------------------------------------------------------------------

    /** The allocations a company may put in a programme: active, in an active partnership, its own. */
    private function cardsQuery(int $businessId)
    {
        return BookableAllocation::query()->active()->where('partner_business_id', $businessId)
            ->whereHas('partnership', fn ($q) => $q->where('status', BusinessPartnership::STATUS_ACTIVE));
    }

    /** The room cards the company can add, each with the price it set. @return array<int,array<string,mixed>> */
    public function roomCards(int $businessId): array
    {
        return $this->cardsQuery($businessId)->with(['bookableItem.lineOption:id,name_ar,name_en', 'ownerBusiness:id,name'])->orderByDesc('id')->get()
            ->filter(fn ($a) => $a->bookableItem && $a->bookableItem->is_active)
            ->map(fn ($a) => $this->allocations->publicRoom($a))->values()->all();
    }

    // ---- presenting ----------------------------------------------------------------------------------------------------

    /**
     * The programme as it is read. `$owner` adds what only its author needs (the draft state, the allocation behind a room item).
     *
     * @return array<string,mixed>
     */
    public function present(TripProgram $program, bool $owner = false): array
    {
        $program->load(['days.items.images']);

        $ids = $program->days->flatMap->items->pluck('allocation_id')->filter()->unique()->values();
        $live = $this->cardsQuery((int) $program->business_id)->whereIn('id', $ids)
            ->with(['bookableItem.lineOption:id,name_ar,name_en', 'ownerBusiness:id,name'])->get()->keyBy('id');

        return [
            'id' => (int) $program->id,
            'business_id' => (int) $program->business_id,
            'title' => (string) $program->title,
            'summary' => $program->summary,
            'status' => (string) $program->status,
            'published_at' => optional($program->published_at)->toIso8601String(),
            'days' => $program->days->map(fn (TripProgramDay $d) => [
                'id' => (int) $d->id,
                'position' => (int) $d->position,
                'title' => $d->title,
                'items' => $d->items->map(fn (TripProgramItem $i) => $this->presentItem($i, $live->get($i->allocation_id), $owner))->values()->all(),
            ])->values()->all(),
        ];
    }

    /** @return array<string,mixed> */
    private function presentItem(TripProgramItem $item, ?BookableAllocation $allocation, bool $owner): array
    {
        $out = ['id' => (int) $item->id, 'position' => (int) $item->position, 'kind' => (string) $item->kind, 'body' => $item->body];

        if ($item->kind === TripProgramItem::KIND_IMAGE) {
            $out['images'] = $item->imagePayload();
        }

        if ($item->kind === TripProgramItem::KIND_ROOM) {
            $room = $allocation && $allocation->bookableItem && $allocation->bookableItem->is_active ? $this->allocations->publicRoom($allocation) : null;
            $out['room'] = $room;
            $out['room_available'] = $room !== null;

            if ($owner) {
                $out['allocation_id'] = (int) $item->allocation_id;
            }
        }

        return $out;
    }

    // ---- ordering ------------------------------------------------------------------------------------------------------

    /** @param Collection<int,\Illuminate\Database\Eloquent\Model> $rows */
    private function renumber(Collection $rows): void
    {
        foreach ($rows->values() as $i => $row) {
            if ((int) $row->position !== $i + 1) {
                $row->update(['position' => $i + 1]);
            }
        }
    }

    /** @param Collection<int,\Illuminate\Database\Eloquent\Model> $rows */
    private function swap(Collection $rows, $row, int $direction): void
    {
        $rows = $rows->values();
        $at = $rows->search(fn ($r) => $r->is($row));
        $to = $at === false ? false : $at + ($direction < 0 ? -1 : 1);

        if ($at === false || $to < 0 || $to >= $rows->count()) {
            return;
        }

        $list = $rows->all();
        [$list[$at], $list[$to]] = [$list[$to], $list[$at]];
        $this->renumber(collect($list));
    }
}
