<?php

namespace App\Services;

use App\Models\OptionGroup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * أىُّ مجموعةٍ أساسُ السعر، وأيُّها تزيد عليه، وأيُّها تُسعَّر وحدها.
 *
 * «هل يمكن فى صفحة اعدادات الحجز ان نظهر مجموعات الخيارات المختارة من البزنس
 * لنفسه ويقوم بتحديد مجموعات كاملة زى الغرف مثلا انها الاساسية لتسعير الخدمة
 * ام الافطار فله سعر منفصل» — المالك، 2026-08-20.
 *
 * الحاجزُ بين «السطر» و«المُوصِّف» مفتوحٌ عمدًا منذ أن طُلب فتحُه: كلُّ كلمةٍ
 * يقولها التاجرُ عن نفسه تصلح للخانتين. لكنّ فتحَه ترك السؤالَ بلا جواب —
 * فمفرداتُ الفندق الثلاث تظهر فى كل قائمة، ويُترك له أن يستنتج الدورَ من
 * الشاشة التى يقف فيها. وهو ما لم يستنتجه، بحقّ.
 *
 * فالإعلانُ مرّةً واحدة، ثم تقرؤه الشاشاتُ الثلاث:
 *
 *     الغرف          → line   الأساس            «غرفة مزدوجة ٩٠٠»
 *     إطلالة الوحدة  → unit   تزيد على الأساس   «+٢٠٠ على D117 وحدها»
 *     نظام الوجبات   → addon  سعرٌ منفصل        «٥٠ × عدد الأفراد»
 *
 * ومجموعةٌ لم تُعلَن تظهر فى الجميع كما كانت: الإعلانُ يضيّق ولا يُشترط، فلا
 * ينكسر تاجرٌ لم يفتح هذه الشاشة قطّ.
 */
class BookingVocabularyRoles
{
    public const ROLE_LINE = 'line';
    public const ROLE_UNIT = 'unit';
    public const ROLE_ADDON = 'addon';

    /** بلا دور: تظهر فى الجميع. */
    public const ROLE_ANY = '';

    public const ROLES = [self::ROLE_LINE, self::ROLE_UNIT, self::ROLE_ADDON];

    /**
     * الأدوارُ المُعلَنة عند هذا النشاط، مفتاحُها معرّفُ المجموعة.
     *
     * @return array<int,string>
     */
    public function for(int $businessId): array
    {
        if ($businessId <= 0) {
            return [];
        }

        return DB::table('business_option_group_roles')
            ->where('business_id', $businessId)
            ->pluck('role', 'option_group_id')
            ->mapWithKeys(fn ($role, $groupId) => [(int) $groupId => (string) $role])
            ->all();
    }

    /**
     * يُعلن الأدوار. المجموعةُ التى تصل بدورٍ فارغ يُمحى إعلانُها.
     *
     * @param  array<int,string>  $roles
     */
    public function save(int $businessId, array $roles): void
    {
        foreach ($roles as $groupId => $role) {
            $groupId = (int) $groupId;
            $role = (string) $role;

            if ($groupId <= 0) {
                continue;
            }

            if (! in_array($role, self::ROLES, true)) {
                DB::table('business_option_group_roles')
                    ->where('business_id', $businessId)
                    ->where('option_group_id', $groupId)
                    ->delete();

                continue;
            }

            DB::table('business_option_group_roles')->updateOrInsert(
                ['business_id' => $businessId, 'option_group_id' => $groupId],
                ['role' => $role, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    /**
     * يُصفّى مفرداتٍ مجموعةً بمجموعة على دورٍ واحد.
     *
     * المفرداتُ تصل من `MerchantOfferingVocabulary` مجموعةً باسمها، فالتصفيةُ
     * تحتاج المعرّفَ لا الاسم — والأسماءُ تتكرّر بين الجذور.
     *
     * ── والإعلانُ يضمّ كما يستبعد ────────────────────────────────────────────
     *
     * مجموعةٌ أعلنها التاجرُ بهذا الدور تدخل ولو لم تكن فى القائمة أصلًا:
     * «مرافق الإقامة» وصفيّةٌ عند المنصّة فتسقط من قوائم التسعير، ومن أعلن
     * أنها «إضافة بسعر منفصل» يقصد أن يبيع دخولَ الجيم — وقولُه عن محلّه أولى
     * من الافتراض. وهو امتدادٌ لقاعدةٍ قائمة: الدورُ ترتيبٌ لا إذن.
     *
     * @param  \Illuminate\Support\Collection  $grouped  [اسم المجموعة => خيارات]
     */
    public function only(Collection $grouped, int $businessId, string $role, ?Collection $everything = null): Collection
    {
        $declared = $this->for($businessId);

        if ($declared === []) {
            return $grouped;
        }

        $names = $this->groupNames(array_keys($declared));

        $kept = $grouped->filter(function ($options, $groupName) use ($declared, $names, $role) {
            $groupId = $names[$groupName] ?? null;

            // لم تُعلَن: تظهر فى الجميع، كما كانت قبل هذه الشاشة.
            if ($groupId === null || ($declared[$groupId] ?? '') === '') {
                return true;
            }

            return $declared[$groupId] === $role;
        });

        if ($everything === null) {
            return $kept;
        }

        // وما أُعلن بهذا الدور ولم تحمله القائمة — «مرافق الإقامة» وأخواتها.
        $wanted = collect($declared)->filter(fn ($r) => $r === $role)->keys()
            ->map(fn ($id) => (int) $id);

        $extra = $everything->filter(function ($options, $groupName) use ($names, $wanted, $kept) {
            $groupId = $names[$groupName] ?? null;

            return $groupId !== null && $wanted->contains($groupId) && ! $kept->has($groupName);
        });

        return $kept->merge($extra);
    }

    /**
     * أسماءُ المجموعات المُعلَنة → معرّفاتها.
     *
     * @param  array<int,int>  $ids
     * @return array<string,int>
     */
    private function groupNames(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return OptionGroup::query()
            ->whereIn('id', $ids)
            ->pluck('id', 'name_ar')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @return array<string,string> ما يُعرض للتاجر عن كل دور */
    public static function labels(): array
    {
        return [
            self::ROLE_LINE => __('أساس السعر'),
            self::ROLE_UNIT => __('تزيد على سعر الوحدة'),
            self::ROLE_ADDON => __('إضافة بسعر منفصل'),
            self::ROLE_ANY => __('بلا تحديد'),
        ];
    }

    /** @return array<string,string> شرحُ كل دور بمثاله */
    public static function hints(): array
    {
        return [
            self::ROLE_LINE => __('ما يُسعَّر أصلًا: «غرفة مزدوجة ٩٠٠». كل وحدة تنتمي لواحد منها.'),
            self::ROLE_UNIT => __('تُثبَّت على غرفة بعينها وتزيد على سعرها: D117 المطلة على البحر ‎+٢٠٠ فتصير ١١٠٠.'),
            self::ROLE_ADDON => __('سعر مستقل يختاره النزيل عند الحجز، ويُضرب في عدد الأفراد إن أشّرت ذلك.'),
        ];
    }
}
