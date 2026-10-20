<?php

namespace App\Services\Projects;

use App\Models\GuaranteeLevel;
use App\Models\Project;
use App\Models\User;
use App\Services\Guarantees\GuaranteeBadgeService;
use Illuminate\Validation\ValidationException;

/**
 * «يشترط المكتب ضمانًا فضيًا على الأقل» (owner, 2026-10-20): a business may require the customer of a project to hold a guarantee
 * of at least a level before work on the plan begins. The level is a code on the project (`required_guarantee_level`); it is
 * judged against the customer's own guarantee badge — the same one shown beside his name — when he agrees to the plan and
 * again when he approves the start of each main stage.
 */
final class ProjectGuaranteeGate
{
    public function __construct(private readonly GuaranteeBadgeService $badges)
    {
    }

    /** The level asked for, as the customer's own kind of guarantee (client / business), or null when nothing is asked. */
    public function requiredFor(Project $project, User $customer): ?GuaranteeLevel
    {
        $code = trim((string) $project->required_guarantee_level);

        if ($code === '') {
            return null;
        }

        return GuaranteeLevel::query()->where('code', $code)->where('target_type', $this->targetOf($customer))->first();
    }

    public function meets(Project $project, User $customer): bool
    {
        $min = $this->requiredFor($project, $customer);

        if (! $min) {
            return true;
        }

        $badge = $this->badges->forUsers([(int) $customer->id])[(int) $customer->id] ?? null;

        return $badge !== null && (int) $badge['priority'] >= (int) $min->priority;
    }

    /** @throws ValidationException */
    public function assertMeets(Project $project, User $customer): void
    {
        if (! $this->meets($project, $customer)) {
            $min = $this->requiredFor($project, $customer);
            $name = $min ? (app()->getLocale() === 'en' ? $min->name_en : $min->name_ar) : (string) $project->required_guarantee_level;

            throw ValidationException::withMessages(['guarantee' => __('يشترط النشاط أن يكون لحسابك ضمان :level على الأقل قبل بدء العمل على الخطة.', ['level' => $name])]);
        }
    }

    /** Is this code a real level? (checked against the business's own kind, so the level exists for customers too). */
    public function isLevel(string $code): bool
    {
        return GuaranteeLevel::query()->where('code', $code)->where('target_type', GuaranteeLevel::TARGET_CLIENT)->exists();
    }

    /** What the customer reads: the level asked for and whether he already has it. */
    public function describe(Project $project, ?User $customer): ?array
    {
        $code = trim((string) $project->required_guarantee_level);

        if ($code === '') {
            return null;
        }

        $min = $customer ? $this->requiredFor($project, $customer) : GuaranteeLevel::query()->where('code', $code)->where('target_type', GuaranteeLevel::TARGET_CLIENT)->first();

        return [
            'code' => $code,
            'name' => $min ? (app()->getLocale() === 'en' ? (string) $min->name_en : (string) $min->name_ar) : $code,
            'customer_ok' => $customer ? $this->meets($project, $customer) : null,
        ];
    }

    private function targetOf(User $user): string
    {
        return (string) $user->type === User::TYPE_BUSINESS ? GuaranteeLevel::TARGET_BUSINESS : GuaranteeLevel::TARGET_CLIENT;
    }
}
