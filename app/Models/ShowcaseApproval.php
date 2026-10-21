<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** The customer's answer for ONE stage of a project / ONE month of a plan, when its showcase asks stage by stage (`consent_mode = each`). */
class ShowcaseApproval extends Model
{
    public const PROJECT_STAGE = 'project_stage';
    public const PLAN_MONTH = 'plan_month';

    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const DECLINED = 'declined';

    protected $fillable = ['kind', 'showcase_id', 'unit_key', 'status', 'decided_at'];

    protected $casts = ['decided_at' => 'datetime'];

    /** @return array<string,string> unit_key => status */
    public static function statuses(string $kind, int $showcaseId): array
    {
        return static::query()->where('kind', $kind)->where('showcase_id', $showcaseId)->pluck('status', 'unit_key')->all();
    }

    public static function decide(string $kind, int $showcaseId, string $unitKey, bool $approve): self
    {
        return static::query()->updateOrCreate(
            ['kind' => $kind, 'showcase_id' => $showcaseId, 'unit_key' => $unitKey],
            ['status' => $approve ? self::APPROVED : self::DECLINED, 'decided_at' => now()]
        );
    }
}
