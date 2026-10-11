<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BusinessStaff;
use App\Models\ServiceChair;
use App\Models\User;
use App\Services\Media\ImageUploadService;
use Illuminate\Http\Request;

/**
 * «الكراسي»: the chairs of a salon with the person at each — what a customer picks from (public) and what the business
 * manages.
 */
final class ServiceChairController extends Controller
{
    use ResolvesOwnerCatalog;

    /** GET /api/v2/discovery/appointments/{business}/chairs — public. */
    public function discover(int $business)
    {
        abort_unless(User::query()->where('type', 'business')->whereKey($business)->exists(), 404, __('النشاط غير موجود.'));

        return response()->json(['success' => true, 'data' => [
            'chairs' => ServiceChair::query()->with('staff:id,name,logo')->where('business_id', $business)->where('is_active', true)
                ->orderBy('sort_order')->orderBy('id')->get()->map->payload()->values(),
        ]]);
    }

    /** GET /api/v2/business/chairs */
    public function index()
    {
        return response()->json(['success' => true, 'data' => [
            'chairs' => ServiceChair::query()->with('staff:id,name,logo')->where('business_id', $this->businessId())
                ->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn (ServiceChair $c) => $c->payload() + ['is_active' => (bool) $c->is_active])->values(),
        ]]);
    }

    /**
     * GET /api/v2/business/chairs/staff — the staff the business may seat at a chair: accepted, active members, with
     * the account's own name and photo.
     */
    public function staff()
    {
        $rows = BusinessStaff::query()
            ->with('user:id,name,logo')
            ->where('business_id', $this->businessId())
            ->where('status', BusinessStaff::STATUS_ACCEPTED)->where('is_active', true)
            ->latest('id')->get();

        return response()->json(['success' => true, 'data' => ['staff' => $rows->filter(fn ($s) => $s->user)->map(fn ($s) => [
            'user_id' => (int) $s->user_id,
            'name' => $s->user->name,
            'photo' => $s->user->logo,
            'title' => $s->title,
        ])->values()]]);
    }

    /** POST /api/v2/business/chairs — multipart: name?, person_name?, staff_user_id?, photo? */
    public function store(Request $request)
    {
        $business = $this->businessId();
        $data = $this->validated($request);

        $chair = ServiceChair::query()->create([
            'business_id' => $business,
            'name' => $data['name'] ?? null,
            'person_name' => $data['person_name'] ?? null,
            'staff_user_id' => $this->staffId($business, $data['staff_user_id'] ?? null),
            'photo' => $request->hasFile('photo') ? app(ImageUploadService::class)->store($request->file('photo')) : null,
            'sort_order' => (int) ServiceChair::query()->where('business_id', $business)->max('sort_order') + 1,
            'is_active' => true,
        ]);

        return response()->json(['success' => true, 'data' => ['chair' => $chair->payload() + ['is_active' => true]]], 201);
    }

    /** POST /api/v2/business/chairs/{chair} — multipart (a file cannot ride a PUT): the fields sent change. */
    public function update(Request $request, int $chair)
    {
        $row = ServiceChair::query()->where('business_id', $this->businessId())->findOrFail($chair);
        $data = $this->validated($request);

        $changes = [];
        foreach (['name', 'person_name'] as $key) {
            if ($request->has($key)) {
                $value = trim((string) ($data[$key] ?? ''));
                $changes[$key] = $value === '' ? null : $value;
            }
        }
        if ($request->has('staff_user_id')) {
            $changes['staff_user_id'] = $this->staffId($this->businessId(), $data['staff_user_id'] ?? null);
        }
        if ($request->has('is_active')) {
            $changes['is_active'] = $request->boolean('is_active');
        }
        if ($request->hasFile('photo')) {
            app(ImageUploadService::class)->delete($row->photo);
            $changes['photo'] = app(ImageUploadService::class)->store($request->file('photo'));
        } elseif ($request->boolean('remove_photo')) {
            app(ImageUploadService::class)->delete($row->photo);
            $changes['photo'] = null;
        }

        $row->update($changes);

        return response()->json(['success' => true, 'data' => ['chair' => $row->payload() + ['is_active' => (bool) $row->is_active]]]);
    }

    /** DELETE /api/v2/business/chairs/{chair} — a chair that ever held a booking is closed instead of removed. */
    public function destroy(int $chair)
    {
        $row = ServiceChair::query()->where('business_id', $this->businessId())->findOrFail($chair);

        if (Booking::query()->where('chair_id', $row->id)->exists()) {
            $row->update(['is_active' => false]);

            return response()->json(['success' => true, 'data' => ['closed' => true]]);
        }

        app(ImageUploadService::class)->delete($row->photo);
        $row->delete();

        return response()->json(['success' => true, 'data' => ['closed' => false]]);
    }

    /** Only an accepted, active staff member of this business may sit at its chair; empty clears the link. */
    private function staffId(int $business, mixed $userId): ?int
    {
        $id = (int) $userId;
        if ($id <= 0) {
            return null;
        }

        abort_unless(
            BusinessStaff::query()->where('business_id', $business)->where('user_id', $id)
                ->where('status', BusinessStaff::STATUS_ACCEPTED)->where('is_active', true)->exists(),
            422,
            __('اختر موظفًا من فريق نشاطك.')
        );

        return $id;
    }

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:80'],
            'person_name' => ['nullable', 'string', 'max:120'],
            'staff_user_id' => ['nullable', 'integer'],
            'photo' => ['nullable', 'file', ...ImageUploadService::validationRules()],
            'is_active' => ['nullable', 'boolean'],
            'remove_photo' => ['nullable', 'boolean'],
        ]);

        abort_if(
            $request->isMethod('post') && ! $request->route('chair')
            && trim((string) ($data['name'] ?? '')) === '' && trim((string) ($data['person_name'] ?? '')) === '' && empty($data['staff_user_id']),
            422,
            __('اكتب اسم الكرسي أو اسم الحلاق.')
        );

        return $data;
    }
}
