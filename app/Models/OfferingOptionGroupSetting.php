<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * فردي (راديو) أو متعدد (تشيك بوكس) لمجموعةِ خياراتٍ عند صاحبِ عرضٍ بعينه —
 * راجع `2026_09_07_054417_create_offering_option_group_settings_table.php`
 * لماذا هذا جدولٌ منفصل لا عمودٌ على `option_groups` العالمية.
 */
class OfferingOptionGroupSetting extends Model
{
    protected $table = 'offering_option_group_settings';

    public const SELECTION_SINGLE = 'single';
    public const SELECTION_MULTIPLE = 'multiple';
    public const SELECTION_TYPES = [self::SELECTION_SINGLE, self::SELECTION_MULTIPLE];

    /**
     * مجموعاتٌ تُختار فيها واحدة افتراضيًا ما لم يقل صاحبُها غير ذلك: «نظام الوجبات» — النزيل إما بلا وجبات أو
     * بنصف إقامة أو بإقامة كاملة، لا اثنتان معًا. (المالك، 2026-10-07)
     */
    public const SINGLE_BY_DEFAULT_GROUPS = ['نظام الوجبات'];

    protected $fillable = ['offering_type', 'offering_id', 'option_group_id', 'selection_type'];

    protected $casts = [
        'offering_id' => 'integer',
        'option_group_id' => 'integer',
    ];

    public function optionGroup(): BelongsTo
    {
        return $this->belongsTo(OptionGroup::class, 'option_group_id');
    }

    /**
     * إعدادُ كل مجموعةٍ عند هذا الصاحب وحده — لواجهة إدارةٍ مُفردة الصاحب،
     * كشاشة إضافات الحجز.
     *
     * @return array<int,string> معرّف المجموعة → نوع الاختيار
     */
    public static function forOwnOffering(string $offeringType, int $offeringId): array
    {
        $stored = static::query()
            ->where('offering_type', $offeringType)
            ->where('offering_id', $offeringId)
            ->pluck('selection_type', 'option_group_id')
            ->map(fn ($type) => (string) $type)
            ->all();

        // ما لم يُكتب شىء: الافتراضُ المنصّىّ (الوجبات واحدة)، وما كُتب يغلبه.
        return $stored + array_fill_keys(static::singleByDefaultGroupIds(), self::SELECTION_SINGLE);
    }

    /** @return list<int> معرّفات المجموعات التى تُختار فيها واحدة افتراضيًا */
    public static function singleByDefaultGroupIds(): array
    {
        static $ids = null;

        return $ids ??= OptionGroup::query()->whereIn('name_ar', self::SINGLE_BY_DEFAULT_GROUPS)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** الافتراضُ المنصّىّ لمجموعةٍ لم يكتب صاحبُها فيها شيئًا. */
    public static function defaultFor(int $groupId): string
    {
        return in_array($groupId, static::singleByDefaultGroupIds(), true) ? self::SELECTION_SINGLE : self::SELECTION_MULTIPLE;
    }

    /**
     * أثرُ الإعداد عند التسعير: يفضّل ما كُتب على صاحبٍ بعينه (سطر سعرٍ) على
     * ما كُتب على النشاط كلِّه — نفسُ ترتيب الأفضلية الذى تقرأ به
     * `ServiceExecutionEngine::modifierRowsFor` صفوفَ `offering_options` نفسها.
     *
     * @param  array<int,array{type:string,id:int}>  $scopes  الأخصّ أولًا
     * @param  \Illuminate\Support\Collection<int,int>  $groupIds
     * @return array<int,string> معرّف المجموعة → نوع الاختيار الفعّال (الافتراضي multiple)
     */
    public static function effectiveFor(array $scopes, $groupIds): array
    {
        $groupIds = collect($groupIds)->filter()->unique()->values();

        if ($groupIds->isEmpty()) {
            return [];
        }

        $rows = static::query()
            ->whereIn('option_group_id', $groupIds->all())
            ->where(function ($query) use ($scopes) {
                foreach ($scopes as $scope) {
                    $query->orWhere(function ($sub) use ($scope) {
                        $sub->where('offering_type', $scope['type'])->where('offering_id', $scope['id']);
                    });
                }
            })
            ->get();

        $effective = [];

        foreach ($scopes as $scope) {
            foreach ($rows->where('offering_type', $scope['type'])->where('offering_id', $scope['id']) as $row) {
                $effective[(int) $row->option_group_id] ??= (string) $row->selection_type;
            }
        }

        // وما لم يكتب فيه أحدٌ شيئًا يأخذ افتراضَ المنصّة.
        foreach ($groupIds as $groupId) {
            $effective[(int) $groupId] ??= static::defaultFor((int) $groupId);
        }

        return $effective;
    }
}
