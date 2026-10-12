<?php

namespace App\Services\Clinics;

use App\Models\ClinicSpecialtyService;
use App\Models\HospitalDoctor;
use App\Models\SpecialtyService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «خدمات التخصص» — a specialty clinic offers what it does (a cleaning, a filling, a filler injection, a laser session…)
 * picked from the platform's list for its specialty (or added by itself), with its OWN price (or none: «السعر بعد
 * الكشف»). A patient reads the list while booking and names the service he came for.
 *
 * Whose list: a single clinic (514) or a medical centre (515) that ticked the specialty. A hospital has its own medical
 * procedures, and a beauty centre never gets these lists — teeth cleaning and whitening are a dentist's, fillers and
 * botox a doctor's. A clinic that ticked two specialties has both lists in one.
 */
class SpecialtyServicesService
{
    /** A single clinic or a medical centre; hospitals are not here. */
    public const CHILDREN = [User::DOCTOR_OWN_CLINIC_CHILD_ID, 515];

    /** specialty => the options (group «تخصصات طبية») that make a clinic one of that specialty. */
    public const OPTIONS = [
        'dental' => ['أسنان'],
        'skin' => ['جلديه وتناسليه', 'جراحة تجميل'],
    ];

    /**
     * The specialties whose list this account has.
     *
     * @return list<string>
     */
    public function specialtiesOf(?User $user): array
    {
        if ($user === null || ! in_array((int) ($user->category_child_id ?? 0), self::CHILDREN, true)) {
            return [];
        }

        $names = array_merge(...array_values(self::OPTIONS));

        $ticked = DB::table('option_user as ou')
            ->join('options as o', 'o.id', '=', 'ou.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('ou.user_id', (int) $user->id)
            ->where('g.name_ar', HospitalDoctor::DEPARTMENT_GROUP)
            ->whereIn('o.name_ar', $names)
            ->pluck('o.name_ar')->all();

        if ($ticked === []) {
            return [];
        }

        return array_values(array_filter(
            array_keys(self::OPTIONS),
            fn (string $specialty) => array_intersect(self::OPTIONS[$specialty], $ticked) !== [],
        ));
    }

    public function hasLists(?User $user): bool
    {
        return $this->specialtiesOf($user) !== [];
    }

    /** @return list<string> the kinds this account's lists are made of, in reading order */
    private function kindsFor(User $clinic): array
    {
        return SpecialtyService::kindsOf($this->specialtiesOf($clinic));
    }

    // ───────────────────────── the clinic's own catalogue ─────────────────────────

    /**
     * Every service the clinic can offer — the platform's lists for its specialties and its own — with what it offers now.
     *
     * @return list<array{kind:string,services:list<array<string,mixed>>}>
     */
    public function catalogOf(User $clinic): array
    {
        $kinds = $this->kindsFor($clinic);
        $offered = ClinicSpecialtyService::query()->where('clinic_id', (int) $clinic->id)->get()->keyBy('specialty_service_id');

        $rows = SpecialtyService::query()->visibleTo((int) $clinic->id)->whereIn('kind', $kinds)
            ->orderBy('sort_order')->orderBy('id')->get();

        $out = [];
        foreach ($kinds as $kind) {
            $out[] = [
                'kind' => $kind,
                'services' => $rows->where('kind', $kind)->map(function (SpecialtyService $s) use ($offered) {
                    $o = $offered->get($s->id);

                    return [
                        'id' => (int) $s->id,
                        'name' => $s->label(),
                        'own' => $s->owner_id !== null,
                        'offered' => $o !== null,
                        'price' => $o?->price,
                    ];
                })->values()->all(),
            ];
        }

        return $out;
    }

    /**
     * Save what the clinic offers: `service_id`, `offered`, and `price` (null/0 = after the exam). Only entries of the
     * platform lists of its specialties or the clinic's own are accepted — never another clinic's.
     *
     * @param  list<array{service_id:int,offered?:bool,price?:float|int|string|null}>  $items
     */
    public function save(User $clinic, array $items): void
    {
        $allowed = SpecialtyService::query()->visibleTo((int) $clinic->id)->whereIn('kind', $this->kindsFor($clinic))->pluck('id')->flip();

        DB::transaction(function () use ($clinic, $items, $allowed) {
            foreach ($items as $item) {
                $id = (int) ($item['service_id'] ?? 0);

                if (! $allowed->has($id)) {
                    throw ValidationException::withMessages(['items' => __('خدمة غير موجودة في القائمة.')]);
                }

                if (! ($item['offered'] ?? true)) {
                    ClinicSpecialtyService::query()->where('clinic_id', $clinic->id)->where('specialty_service_id', $id)->delete();

                    continue;
                }

                $price = isset($item['price']) && is_numeric($item['price']) && (float) $item['price'] > 0 ? round((float) $item['price'], 2) : null;

                ClinicSpecialtyService::query()->updateOrCreate(
                    ['clinic_id' => (int) $clinic->id, 'specialty_service_id' => $id],
                    ['price' => $price],
                );
            }
        });
    }

    /** A service the platform list does not have: the clinic's own, under one of its kinds, offered at once. */
    public function addOwn(User $clinic, string $kind, string $name): SpecialtyService
    {
        $name = trim($name);

        if (! in_array($kind, $this->kindsFor($clinic), true) || $name === '') {
            throw ValidationException::withMessages(['name' => __('اكتب اسم الخدمة واختر قسمها.')]);
        }

        if (SpecialtyService::query()->visibleTo((int) $clinic->id)->where('kind', $kind)->where('name_ar', $name)->exists()) {
            throw ValidationException::withMessages(['name' => __('هذه الخدمة موجودة في القائمة.')]);
        }

        $row = SpecialtyService::create([
            'specialty' => SpecialtyService::specialtyOfKind($kind),
            'kind' => $kind,
            'name_ar' => $name,
            'owner_id' => (int) $clinic->id,
            'sort_order' => 999,
        ]);
        ClinicSpecialtyService::create(['clinic_id' => (int) $clinic->id, 'specialty_service_id' => (int) $row->id]);

        return $row;
    }

    public function deleteOwn(User $clinic, SpecialtyService $service): void
    {
        abort_unless((int) $service->owner_id === (int) $clinic->id, 404);

        $service->delete();
    }

    // ───────────────────────── what a patient sees ─────────────────────────

    /**
     * What the clinic offers, by kind; kinds with nothing offered are left out.
     *
     * @return list<array{kind:string,services:list<array<string,mixed>>}>
     */
    public function offeredBy(User $clinic): array
    {
        $kinds = $this->kindsFor($clinic);

        $rows = ClinicSpecialtyService::query()->where('clinic_id', (int) $clinic->id)->with('service')->get()
            ->filter(fn (ClinicSpecialtyService $o) => $o->service !== null && in_array($o->service->kind, $kinds, true));

        $out = [];
        foreach ($kinds as $kind) {
            $in = $rows->filter(fn (ClinicSpecialtyService $o) => $o->service->kind === $kind)
                ->sortBy(fn (ClinicSpecialtyService $o) => [$o->service->sort_order, $o->id]);

            if ($in->isEmpty()) {
                continue;
            }

            $out[] = [
                'kind' => $kind,
                'services' => $in->map(fn (ClinicSpecialtyService $o) => [
                    'id' => (int) $o->specialty_service_id,
                    'name' => $o->service->label(),
                    'price' => $o->price,
                ])->values()->all(),
            ];
        }

        return $out;
    }

    public function hasOffers(User $clinic): bool
    {
        return $this->offeredBy($clinic) !== [];
    }

    /** Is this one of the services the clinic offers right now (its specialties still ticked)? */
    public function isOffered(User $clinic, int $serviceId): ?ClinicSpecialtyService
    {
        $offer = ClinicSpecialtyService::query()->where('clinic_id', (int) $clinic->id)
            ->where('specialty_service_id', $serviceId)->with('service')->first();

        if ($offer === null || $offer->service === null || ! in_array($offer->service->kind, $this->kindsFor($clinic), true)) {
            return null;
        }

        return $offer;
    }
}
