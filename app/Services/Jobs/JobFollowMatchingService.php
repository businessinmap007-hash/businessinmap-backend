<?php

namespace App\Services\Jobs;

use App\Models\JobFollow;
use App\Models\Post;
use App\Services\Notifications\NotificationDispatcherService;
use Illuminate\Support\Facades\DB;

/**
 * When a business posts a job, notify every user who follows that field.
 *
 * A follow matches the job if it targets the job's specialty
 * (category_child_id) OR the job's whole root category (category_id, child
 * null). The poster never notifies themselves. Dedup is per (follow, job) via
 * the app_notifications source_type/source_id the dispatcher writes — plus a
 * belt-and-braces in-memory guard so one user with both a category and a
 * child follow on the same job is pinged once.
 */
final class JobFollowMatchingService
{
    public function __construct(private readonly NotificationDispatcherService $dispatcher)
    {
    }

    /** @return int number of users notified */
    public function notifyForJob(Post $job): int
    {
        if ($job->type !== 'job') {
            return 0;
        }

        $follows = JobFollow::query()
            ->where('is_active', true)
            ->where('user_id', '!=', (int) $job->user_id)
            ->where(function ($w) use ($job) {
                if ($job->category_child_id) {
                    $w->where('category_child_id', (int) $job->category_child_id);
                }
                if ($job->category_id) {
                    $w->orWhere(function ($x) use ($job) {
                        $x->where('category_id', (int) $job->category_id)
                            ->whereNull('category_child_id');
                    });
                }
            })
            ->get();

        $title = $job->title ?: 'وظيفة جديدة';
        $notified = [];

        foreach ($follows as $follow) {
            $userId = (int) $follow->user_id;

            if (isset($notified[$userId])) {
                continue;
            }
            $notified[$userId] = true;

            $this->dispatcher->dispatch('job_posted', $userId, [
                'actor_id' => (int) $job->user_id,
                'title_ar' => 'وظيفة جديدة في مجال تتابعه',
                'title_en' => 'New job in a field you follow',
                // One authored title, so it stands in for both languages.
                'body_ar' => $title,
                'body_en' => $job->title ?: 'New job',
                'action_type' => 'open_job',
                'action_url' => '/jobs/' . $job->id,
                'notifiable_type' => Post::class,
                'notifiable_id' => (int) $job->id,
                'source_type' => 'job_posted',
                'source_id' => (int) $job->id,
                'meta' => [
                    'job_id' => (int) $job->id,
                    'category_id' => $job->category_id ? (int) $job->category_id : null,
                    'category_child_id' => $job->category_child_id ? (int) $job->category_child_id : null,
                ],
            ]);
        }

        if ($follows->isNotEmpty()) {
            JobFollow::query()
                ->whereIn('id', $follows->pluck('id'))
                ->update(['last_matched_at' => now()]);
        }

        return count($notified);
    }
}
