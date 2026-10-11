<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Http\Controllers\Controller;
use App\Models\BusinessServicePrice;
use App\Models\CourseGroup;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * «كورس»: the groups a course runs in — what the customer picks from (public) and what the business manages.
 */
final class CourseGroupController extends Controller
{
    use ResolvesOwnerCatalog;

    /** The platform item type a course is sold under. */
    private const COURSE_TYPE = 'book_a_course';

    /**
     * GET /api/v2/discovery/courses/{business} — public. Each course of the business with its groups that have not
     * ended, each with the seats left.
     */
    public function discover(int $business)
    {
        abort_unless(User::query()->where('type', 'business')->whereKey($business)->exists(), 404, __('النشاط غير موجود.'));

        $today = now()->toDateString();
        $groups = CourseGroup::query()
            ->where('business_id', $business)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereDate('ends_on', '>=', $today)->orWhere(fn ($w) => $w->whereNull('ends_on')->whereDate('starts_on', '>=', $today)))
            ->orderBy('starts_on')->orderBy('id')
            ->get()->groupBy('offering_id');

        $courses = $this->coursesOf($business)->map(function (BusinessServicePrice $course) use ($groups) {
            return [
                'id' => (int) $course->id,
                'service_id' => (int) $course->service_id,
                'name' => $this->nameOf($course),
                'price' => (float) $course->price,
                'currency' => $course->currency ?: 'EGP',
                'groups' => ($groups->get($course->id) ?? collect())->map(fn (CourseGroup $g) => $this->groupPayload($g))->values(),
            ];
        })->filter(fn ($c) => $c['groups']->isNotEmpty())->values();

        return response()->json(['success' => true, 'data' => ['courses' => $courses]]);
    }

    /** GET /api/v2/business/course-groups — the business's own: its courses and every group, taken seats included. */
    public function index()
    {
        $business = $this->businessId();
        $groups = CourseGroup::query()->where('business_id', $business)->orderByDesc('starts_on')->orderBy('id')->get();

        return response()->json(['success' => true, 'data' => [
            'courses' => $this->coursesOf($business)->map(fn (BusinessServicePrice $c) => [
                'id' => (int) $c->id, 'name' => $this->nameOf($c), 'price' => (float) $c->price,
            ])->values(),
            'groups' => $groups->map(fn (CourseGroup $g) => $this->groupPayload($g) + ['is_active' => (bool) $g->is_active])->values(),
        ]]);
    }

    /** POST /api/v2/business/course-groups */
    public function store(Request $request)
    {
        $business = $this->businessId();
        $data = $this->validated($request, $business);

        $group = CourseGroup::query()->create($data + ['business_id' => $business, 'is_active' => true]);

        return response()->json(['success' => true, 'data' => ['group' => $this->groupPayload($group) + ['is_active' => true]]], 201);
    }

    /** PUT /api/v2/business/course-groups/{group} */
    public function update(Request $request, int $group)
    {
        $business = $this->businessId();
        $row = CourseGroup::query()->where('business_id', $business)->findOrFail($group);

        $data = $this->validated($request, $business);
        if ($request->has('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        }

        // fewer seats than are already taken would leave a booking without a seat
        abort_if(isset($data['seats']) && $data['seats'] < $row->seatsTaken(), 422, __('عدد المقاعد أقل من المحجوز بالفعل.'));

        $row->update($data);

        return response()->json(['success' => true, 'data' => ['group' => $this->groupPayload($row->fresh()) + ['is_active' => (bool) $row->is_active]]]);
    }

    /** DELETE /api/v2/business/course-groups/{group} — a group nobody joined; one with seats taken is closed instead. */
    public function destroy(int $group)
    {
        $row = CourseGroup::query()->where('business_id', $this->businessId())->findOrFail($group);

        abort_if($row->seatsTaken() > 0, 422, __('انضم إلى هذه المجموعة طلاب — أغلقها بدل حذفها.'));

        $row->delete();

        return response()->json(['success' => true]);
    }

    /** @return \Illuminate\Support\Collection<int,BusinessServicePrice> */
    private function coursesOf(int $business)
    {
        return BusinessServicePrice::query()
            ->where('business_id', $business)
            ->where('bookable_item_type', self::COURSE_TYPE)
            ->where('is_active', 1)
            ->orderBy('sort_order')->orderBy('id')
            ->get();
    }

    private function nameOf(BusinessServicePrice $course): string
    {
        $label = DB::table('offering_options as oo')
            ->join('options as o', 'o.id', '=', 'oo.option_id')
            ->where('oo.offering_type', $course->getMorphClass())
            ->where('oo.offering_id', $course->id)
            ->where('oo.role', 'line')
            ->selectRaw('COALESCE(NULLIF(o.name_' . (app()->getLocale() === 'en' ? 'en' : 'ar') . ", ''), o.name_ar) as n")
            ->value('n');

        return (string) ($label ?: __('كورس'));
    }

    /** @return array<string,mixed> */
    private function groupPayload(CourseGroup $g): array
    {
        $taken = $g->seatsTaken();

        return [
            'id' => (int) $g->id,
            'offering_id' => (int) $g->offering_id,
            'name' => $g->name,
            'level' => $g->level,
            'schedule_text' => $g->schedule_text,
            'starts_on' => $g->starts_on?->toDateString(),
            'ends_on' => $g->ends_on?->toDateString(),
            'seats' => (int) $g->seats,
            'seats_taken' => $taken,
            'seats_left' => max((int) $g->seats - $taken, 0),
        ];
    }

    /** @return array<string,mixed> */
    private function validated(Request $request, int $business): array
    {
        $data = $request->validate([
            'offering_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:120'],
            'level' => ['nullable', 'string', 'max:40'],
            'schedule_text' => ['nullable', 'string', 'max:160'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'seats' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        // only a course of this very business
        abort_unless($this->coursesOf($business)->contains('id', (int) $data['offering_id']), 422, __('اختر كورسًا من كورسات نشاطك.'));

        return $data;
    }
}
