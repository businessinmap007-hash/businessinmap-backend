<?php

namespace App\Services\Moderation;

use App\Models\Comment;
use App\Models\ContentReport;
use App\Models\OwnerListing;
use App\Models\Post;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcherService;
use App\Services\OwnerListings\OwnerListingService;
use App\Services\OwnerListings\OwnerListingSeriousness;
use App\Support\AdminAbility;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * THE report core (owner, 2026-10-27): any account reports any post or owner listing; a moderator — any account holding
 * the content ability ({@see AdminAbility::CONTENT}) — sees what was reported, once per item, and either dismisses it
 * or removes the item.
 *
 * What «remove» means depends on the item: a post is hidden (reversible, `is_active`), an owner listing is deleted with
 * its photos and chats. A new kind of content is one entry in {@see self::TYPES} plus its three small cases below.
 */
final class ContentReportService
{
    /** key → model class. The key is what the app sends and what the table stores. */
    public const TYPES = [
        'post' => Post::class,
        'comment' => Comment::class,
        // an ACCOUNT is never removed: a moderator may chat with it and freeze it for three days
        'account' => User::class,
        'owner_listing' => OwnerListing::class,
    ];

    /** reason → [Arabic, English]. */
    public const REASONS = [
        'spam' => ['مزعج أو متكرر', 'Spam or repeated'],
        'fraud' => ['احتيال أو معلومات مضللة', 'Fraud or misleading'],
        'inappropriate' => ['محتوى غير لائق', 'Inappropriate content'],
        'prohibited' => ['سلعة أو خدمة ممنوعة', 'Prohibited item or service'],
        'wrong_info' => ['بيانات أو صور غير صحيحة', 'Wrong details or photos'],
        'other' => ['سبب آخر', 'Something else'],
    ];

    public function __construct(
        private readonly AccountModerationService $accounts,
        private readonly OwnerListingService $listings,
        private readonly NotificationDispatcherService $notifications
    ) {}

    /** Whether this account gets the moderation tab. */
    public function isModerator(User $user): bool
    {
        return $user->can(AdminAbility::CONTENT);
    }

    /** @return list<array{key:string,name:string}> */
    public function reasons(bool $english): array
    {
        $out = [];

        foreach (self::REASONS as $key => $names) {
            $out[] = ['key' => $key, 'name' => $names[$english ? 1 : 0]];
        }

        return $out;
    }

    // ─────────────────────────── reporting ───────────────────────────

    /** One person reports one item; a second report of the same item by the same person changes nothing. */
    public function report(User $reporter, string $type, int $id, string $reason, ?string $details = null): ContentReport
    {
        $target = $this->find($type, $id);

        if (! $target || ! $this->isReportable($type, $target)) {
            throw ValidationException::withMessages(['target' => __('هذا المحتوى غير متاح.')]);
        }

        if ((int) $this->authorId($type, $target) === (int) $reporter->id) {
            throw ValidationException::withMessages(['target' => __('لا يمكنك الإبلاغ عن محتواك.')]);
        }

        if (! isset(self::REASONS[$reason])) {
            $reason = 'other';
        }

        $report = ContentReport::query()->firstOrCreate(
            ['reporter_id' => (int) $reporter->id, 'reportable_type' => $type, 'reportable_id' => $id],
            ['reason' => $reason, 'details' => $details !== null && trim($details) !== '' ? mb_substr(trim($details), 0, 500) : null, 'status' => ContentReport::STATUS_OPEN]
        );

        if ($type === 'owner_listing' && $report->wasRecentlyCreated) {
            $this->flagListingIfEnough($target);
        }

        return $report;
    }

    /** Enough different people reporting a listing hide it from the public until a moderator looks. */
    private function flagListingIfEnough(OwnerListing $listing): void
    {
        $count = $this->openCount('owner_listing', (int) $listing->id);
        $listing->reports_count = $count;

        if ($count >= (int) config('bim.owner_listings.reports_to_hide', 3) && $listing->status === OwnerListing::STATUS_ACTIVE) {
            $listing->status = OwnerListing::STATUS_FLAGGED;
        }

        $listing->save();
    }

    // ─────────────────────────── moderation ───────────────────────────

    /**
     * The queue: ONE row per reported item (not per report), most-reported first.
     *
     * @return LengthAwarePaginator<int,array<string,mixed>>
     */
    public function queue(string $scope, ?string $type, int $perPage, bool $english, ?User $viewer = null): LengthAwarePaginator
    {
        $page = DB::table('content_reports')
            ->when($scope === 'open', fn ($q) => $q->where('status', ContentReport::STATUS_OPEN))
            ->when($scope !== 'open', fn ($q) => $q->where('status', '!=', ContentReport::STATUS_OPEN))
            ->when($type !== null && isset(self::TYPES[$type]), fn ($q) => $q->where('reportable_type', $type))
            ->select('reportable_type', 'reportable_id', DB::raw('COUNT(*) as reports'), DB::raw('MAX(created_at) as last_at'))
            ->groupBy('reportable_type', 'reportable_id')
            ->orderByDesc('reports')->orderByDesc('last_at')
            ->paginate($perPage);

        $page->getCollection()->transform(fn ($row) => $this->row((string) $row->reportable_type, (int) $row->reportable_id, (int) $row->reports, (string) $row->last_at, $scope, $english, $viewer));

        return $page;
    }

    /**
     * A moderator opens a report: from now on it is recorded that HE is dealing with it, and nobody else may act on it until he
     * closes it (dismiss / remove) or lets it go ({@see self::release()}). A claim nobody touches for
     * `bim.moderation.claim_minutes` lapses, so a moderator who walks away cannot freeze a report for good.
     */
    public function claim(string $type, int $id, User $moderator): void
    {
        $this->guard($type, $id, $moderator, claimOnly: true);
    }

    /** He lets it go without closing it: the report is free for any moderator again. */
    public function release(string $type, int $id, User $moderator): void
    {
        $holder = $this->holder($type, $id);

        if ($holder !== null && $holder['id'] !== (int) $moderator->id) {
            throw ValidationException::withMessages(['claim' => __(':name يتعامل مع هذا البلاغ الآن.', ['name' => $holder['name']])]);
        }

        $this->openReports($type, $id)->update(['claimed_by' => null, 'claimed_at' => null]);
    }

    /** Whoever holds a live claim on this item's open reports, or null. @return array{id:int,name:string}|null */
    public function holder(string $type, int $id): ?array
    {
        $row = $this->openReports($type, $id)->whereNotNull('claimed_by')
            ->where('claimed_at', '>', now()->subMinutes((int) config('bim.moderation.claim_minutes', 30)))
            ->latest('claimed_at')->first(['claimed_by']);

        if (! $row) {
            return null;
        }

        $user = User::query()->whereKey($row->claimed_by)->first();

        return ['id' => (int) $row->claimed_by, 'name' => $user ? $user->displayName() : '#' . $row->claimed_by];
    }

    /** The item must have open reports; someone else holding it stops the call; otherwise it is (re)claimed for this moderator. */
    private function guard(string $type, int $id, User $moderator, bool $claimOnly = false): void
    {
        if (! $this->openReports($type, $id)->exists()) {
            throw ValidationException::withMessages(['claim' => __('لا توجد بلاغات مفتوحة على هذا المحتوى.')]);
        }

        $holder = $this->holder($type, $id);

        if ($holder !== null && $holder['id'] !== (int) $moderator->id) {
            throw ValidationException::withMessages(['claim' => __(':name يتعامل مع هذا البلاغ الآن.', ['name' => $holder['name']])]);
        }

        $this->openReports($type, $id)->update(['claimed_by' => (int) $moderator->id, 'claimed_at' => now()]);
    }

    private function openReports(string $type, int $id)
    {
        return ContentReport::query()->where('reportable_type', $type)->where('reportable_id', $id)->where('status', ContentReport::STATUS_OPEN);
    }

    /** Whoever reported it hears what came of it — never who decided. */
    private function tellReporters(array $reporterIds, string $type, int $id, bool $removed): void
    {
        foreach ($reporterIds as $reporterId) {
            try {
                $this->notifications->dispatch('moderation_report_outcome', (int) $reporterId, [
                    'body_ar' => $removed ? 'راجع المشرف بلاغك وأزال المحتوى. شكرًا لمساعدتك في إبقاء المنصة نظيفة.' : 'راجع المشرف بلاغك ورأى أن المحتوى لا يخالف القواعد. شكرًا لك.',
                    'body_en' => $removed ? 'A moderator reviewed your report and removed the content. Thank you for helping keep the platform clean.' : 'A moderator reviewed your report and found the content within the rules. Thank you.',
                    'source_type' => 'moderation_report_outcome',
                    'source_id' => $id,
                    'meta' => ['type' => $type, 'id' => $id, 'removed' => $removed],
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /** The author hears that his content was taken down — for a listing, which rule it broke and what was confiscated. */
    private function tellAuthor(string $type, $target, ?string $violation = null, float $taken = 0.0, ?string $kind = null): void
    {
        try {
            $title = $type === 'comment' ? '' : (string) ($target->title ?? '');
            $kindAr = ['post' => 'منشورك', 'comment' => 'تعليقك'][$type] ?? 'إعلانك';
            $kindEn = ['post' => 'Your post', 'comment' => 'Your comment'][$type] ?? 'Your listing';
            $ar = trim($kindAr . ($title !== '' ? " «{$title}»" : '') . ' أُزيل بعد بلاغات رأى المشرف أنها في محلها.');
            $en = trim($kindEn . ($title !== '' ? " \"{$title}\"" : '') . ' was taken down after reports a moderator upheld.');

            if ($type === 'owner_listing' && isset(OwnerListingSeriousness::VIOLATIONS[(string) $violation])) {
                [$ruleAr, $ruleEn] = OwnerListingSeriousness::VIOLATIONS[$violation];
                $sum = rtrim(rtrim(number_format($taken, 2, '.', ''), '0'), '.');
                $thing = $kind === OwnerListingSeriousness::KIND_GUARANTEE ? ['الضمان', 'guarantee'] : ['العربون', 'deposit'];
                $ar .= " السبب: {$ruleAr}." . ($taken > 0 ? " صودر {$thing[0]} ({$sum} جنيه)." : ' لم يُصادَر شيء.');
                $en .= " Reason: {$ruleEn}." . ($taken > 0 ? " The {$thing[1]} ({$sum} EGP) was confiscated." : ' Nothing was confiscated.');
            }

            $this->notifications->dispatch('moderation_content_removed', (int) $target->user_id, [
                'body_ar' => $ar,
                'body_en' => $en,
                'source_type' => 'moderation_content_removed',
                'source_id' => (int) $target->id,
                'meta' => ['type' => $type, 'id' => (int) $target->id, 'violation' => $violation, 'confiscated' => $taken],
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Not a problem after all: the reports are closed and a hidden listing comes back. */
    public function dismiss(string $type, int $id, User $moderator): void
    {
        $this->guard($type, $id, $moderator);
        $this->closeReports($type, $id, $moderator, ContentReport::STATUS_DISMISSED, null);

        $target = $this->find($type, $id);

        if ($type === 'account' && $target instanceof User && ! $target->isFrozen()) {
            \App\Models\AccountCase::query()->where('user_id', $id)->where('status', 'open')->get()
                ->each(fn ($c) => $this->accounts->close($c, $moderator, 'cleared'));
        }

        if ($type === 'owner_listing' && $target instanceof OwnerListing && $target->status === OwnerListing::STATUS_FLAGGED) {
            $target->update(['status' => OwnerListing::STATUS_ACTIVE, 'reports_count' => 0]);
        }
    }

    /**
     * A post is hidden, a comment is hidden, a listing is deleted. For a LISTING the moderator names the rule it broke
     * ({@see OwnerListingSeriousness::VIOLATIONS}) and, unless he chooses otherwise, the stake behind it — a held deposit or the
     * frozen guarantee — is confiscated. Returns the sum confiscated.
     */
    public function remove(string $type, int $id, User $moderator, ?string $violation = null, bool $confiscate = true): float
    {
        $target = $this->find($type, $id);

        if (! $target) {
            throw ValidationException::withMessages(['target' => __('هذا المحتوى محذوف بالفعل.')]);
        }

        if ($type === 'account') {
            throw ValidationException::withMessages(['target' => __('الحسابات لا تُزال؛ تُجمَّد ثلاثة أيام.')]);
        }

        if ($type === 'owner_listing' && ! isset(OwnerListingSeriousness::VIOLATIONS[(string) $violation])) {
            throw ValidationException::withMessages(['violation' => __('اختر القاعدة التي خالفها الإعلان.')]);
        }

        $this->guard($type, $id, $moderator);
        $kind = $target->deposit_kind ?? null;
        $taken = 0.0;

        DB::transaction(function () use ($type, $id, $target, $moderator, $violation, $confiscate, &$taken) {
            match ($type) {
                'post' => $target->update(['is_active' => false]),
                'comment' => $target->update(['status' => 'removed']),
                default => $taken = $this->listings->removeForViolation($target, (string) $violation, $confiscate),
            };

            $this->closeReports($type, $id, $moderator, ContentReport::STATUS_ACTIONED, 'removed', ['violation' => $violation, 'confiscated_amount' => $taken]);
        });

        $this->tellAuthor($type, $target, $violation, $taken, $kind);

        return $taken;
    }

    /** The moderator opens a chat with the reported account, to find out what the report is about. */
    public function chatWithAccount(int $id, User $moderator): \App\Models\AccountCase
    {
        $account = $this->accountOrFail($id);
        $this->guard('account', $id, $moderator);

        return $this->accounts->openChat($account, $moderator);
    }

    /**
     * Freeze the reported account (never removes it, except that the fifth freeze deletes it and confiscates its balance and
     * guarantee); the reports are closed as actioned.
     *
     * @return array{case:\App\Models\AccountCase,deleted:bool,count:int,confiscated:float}
     */
    public function freezeAccount(int $id, User $moderator): array
    {
        $account = $this->accountOrFail($id);
        $this->guard('account', $id, $moderator);

        return DB::transaction(function () use ($account, $id, $moderator) {
            $result = $this->accounts->freeze($account, $moderator);
            $this->closeReports('account', $id, $moderator, ContentReport::STATUS_ACTIONED, $result['deleted'] ? 'deleted' : 'frozen', ['confiscated_amount' => $result['confiscated']]);

            return $result;
        });
    }

    /** The moderator lifts the freeze early. */
    public function unfreezeAccount(int $id, User $moderator): void
    {
        $this->accounts->unfreeze($this->accountOrFail($id), $moderator);
    }

    private function accountOrFail(int $id): User
    {
        $account = User::query()->find($id);

        if (! $account) {
            throw ValidationException::withMessages(['target' => __('هذا المحتوى محذوف بالفعل.')]);
        }

        return $account;
    }

    /** A hidden post goes back up (a moderator changed their mind). */
    public function restore(string $type, int $id, User $moderator): void
    {
        $target = $this->find($type, $id);

        if ($type === 'account' && $target) {
            $this->accounts->unfreeze($target, $moderator);

            return;
        }

        if ($type !== 'post' || ! $target) {
            throw ValidationException::withMessages(['target' => __('لا يمكن استعادة هذا المحتوى.')]);
        }

        $target->update(['is_active' => true]);
        ContentReport::query()->where('reportable_type', $type)->where('reportable_id', $id)->where('action', 'removed')
            ->update(['status' => ContentReport::STATUS_DISMISSED, 'action' => null, 'handled_by' => (int) $moderator->id, 'handled_at' => now()]);
    }

    private function closeReports(string $type, int $id, User $moderator, string $status, ?string $action, array $extra = []): void
    {
        $reporters = $this->openReports($type, $id)->pluck('reporter_id')->unique()->all();

        ContentReport::query()->where('reportable_type', $type)->where('reportable_id', $id)->where('status', ContentReport::STATUS_OPEN)
            ->update(['status' => $status, 'action' => $action, 'handled_by' => (int) $moderator->id, 'handled_at' => now()] + $extra);

        $this->tellReporters($reporters, $type, $id, $status === ContentReport::STATUS_ACTIONED);
    }

    // ─────────────────────────── the items ───────────────────────────

    /** @return Post|OwnerListing|null */
    public function find(string $type, int $id)
    {
        $class = self::TYPES[$type] ?? null;

        return $class ? $class::query()->find($id) : null;
    }

    private function isReportable(string $type, $target): bool
    {
        return match ($type) {
            'post' => (bool) $target->is_active && ($target->expire_at === null || $target->expire_at->isFuture()),
            // only a public comment: a private one is a conversation between two people, not published content
            'comment' => $target->status === 'public',
            // any ordinary account: not a staff account, not one that is already banned or gone
            'account' => (string) $target->type !== 'admin' && ! $target->isBanned() && $target->anonymized_at === null,
            default => in_array($target->status, [OwnerListing::STATUS_ACTIVE, OwnerListing::STATUS_FLAGGED], true),
        };
    }

    private function authorId(string $type, $target): int
    {
        return $type === 'account' ? (int) $target->id : (int) $target->user_id;
    }

    private function openCount(string $type, int $id): int
    {
        return (int) ContentReport::query()->where('reportable_type', $type)->where('reportable_id', $id)->where('status', ContentReport::STATUS_OPEN)->count();
    }

    /** @return array<string,mixed> */
    private function row(string $type, int $id, int $reports, string $lastAt, string $scope, bool $english, ?User $viewer): array
    {
        $target = $this->find($type, $id);
        $reasons = DB::table('content_reports')->where('reportable_type', $type)->where('reportable_id', $id)
            ->when($scope === 'open', fn ($q) => $q->where('status', ContentReport::STATUS_OPEN), fn ($q) => $q->where('status', '!=', ContentReport::STATUS_OPEN))
            ->select('reason', DB::raw('COUNT(*) as n'))->groupBy('reason')->orderByDesc('n')->get()
            ->map(fn ($r) => ['key' => $r->reason, 'name' => (self::REASONS[$r->reason] ?? self::REASONS['other'])[$english ? 1 : 0], 'count' => (int) $r->n])->all();

        $details = DB::table('content_reports')->where('reportable_type', $type)->where('reportable_id', $id)->whereNotNull('details')
            ->orderByDesc('id')->limit(3)->pluck('details')->all();

        $base = ['type' => $type, 'id' => $id, 'reports' => $reports, 'last_reported_at' => \Illuminate\Support\Carbon::parse($lastAt)->toIso8601String(), 'reasons' => $reasons, 'details' => $details];

        if ($scope === 'open') {
            // who is dealing with it now (recorded when a moderator opened it), and whether that is the one asking
            $holder = $this->holder($type, $id);
            $base += [
                'handler' => $holder,
                'claimed_by_me' => $holder !== null && $viewer !== null && $holder['id'] === (int) $viewer->id,
                'locked' => $holder !== null && ($viewer === null || $holder['id'] !== (int) $viewer->id),
            ];
        } else {
            $closedBy = ContentReport::query()->where('reportable_type', $type)->where('reportable_id', $id)->whereNotNull('handled_by')->latest('handled_at')->value('handled_by');
            $base += ['handled_by' => $closedBy ? User::query()->whereKey($closedBy)->first()?->displayName() : null];
        }

        if (! $target) {
            return $base + ['state' => 'gone', 'title' => null, 'excerpt' => null, 'image' => null, 'author' => null];
        }

        $author = User::query()->whereKey($target->user_id)->first();

        if ($type === 'post') {
            return $base + [
                'state' => $target->is_active ? 'live' : 'hidden',
                'title' => (string) ($target->title ?? ''),
                'excerpt' => mb_substr(trim((string) $target->body), 0, 240),
                'image' => $target->image ?: null,
                'author' => $author ? ['id' => (int) $author->id, 'name' => $author->displayName()] : null,
                'post_type' => $target->type,
            ];
        }

        if ($type === 'account') {
            $case = \App\Models\AccountCase::query()->where('user_id', $id)->latest('id')->first();

            return $base + [
                'state' => $target->isFrozen() ? 'frozen' : 'live',
                'title' => $target->displayName(),
                'excerpt' => mb_substr(trim((string) $target->about), 0, 240),
                'image' => $target->logo ?: ($target->image ?: null),
                'author' => null,
                'account_type' => (string) $target->type,
                'frozen_until' => $target->isFrozen() ? $target->frozen_until->toIso8601String() : null,
                'freeze_count' => (int) $target->freeze_count,
                'freezes_to_delete' => (int) config('bim.moderation.freezes_to_delete', 5),
                'case' => $case ? ['id' => (int) $case->id, 'status' => $case->status, 'chat_opened' => true] : null,
            ];
        }

        if ($type === 'comment') {
            return $base + [
                'state' => $target->status === 'removed' ? 'hidden' : 'live',
                'title' => null,
                'excerpt' => mb_substr(trim((string) $target->comment), 0, 240),
                'image' => null,
                'author' => $author ? ['id' => (int) $author->id, 'name' => $author->displayName()] : null,
                'post_id' => (int) $target->post_id,
            ];
        }

        return $base + [
            'state' => $target->status === OwnerListing::STATUS_ACTIVE ? 'live' : 'hidden',
            'title' => (string) $target->title,
            'excerpt' => mb_substr(trim((string) $target->description), 0, 240),
            'image' => optional($target->images()->first())->image,
            'author' => $author ? ['id' => (int) $author->id, 'name' => $author->displayName()] : null,
            'price' => $target->price !== null ? (float) $target->price : null,
        ];
    }
}
