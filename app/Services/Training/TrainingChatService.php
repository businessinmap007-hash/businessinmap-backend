<?php

namespace App\Services\Training;

use App\Models\ThreadParticipant;
use App\Models\TrainingPlan;
use App\Models\Thread;
use App\Services\ThreadService;

/**
 * A direct, TEXT-ONLY chat between a trainer and their trainee, scoped to a
 * training plan. It rides the generic threads system (encryption at rest, the
 * admin moderation hub, chat_message push) exactly like the operation chat —
 * but attachments are never offered here: a trainee↔trainer image channel is a
 * harassment risk the training section deliberately excludes.
 */
class TrainingChatService
{
    public function __construct(private readonly ThreadService $threads)
    {
    }

    /** Open (or return) the plan's chat, seating the client and the trainer. */
    public function open(TrainingPlan $plan): Thread
    {
        $existed = Thread::query()
            ->where('subject_type', $plan->getMorphClass())
            ->where('subject_id', $plan->getKey())
            ->exists();

        $thread = $this->threads->forSubject($plan, [
            ['user_id' => (int) $plan->client_id, 'role' => ThreadParticipant::ROLE_CLIENT],
            ['user_id' => (int) $plan->trainer_id, 'role' => ThreadParticipant::ROLE_BUSINESS],
        ], false);

        if (! $existed) {
            $this->threads->system(
                $thread,
                'فُتحت محادثة مباشرة بين المدرّب والمتدرّب حول خطة التدريب. الرسائل نصية فقط.'
            );
        }

        return $thread;
    }

    /** Post a TEXT message; ThreadService notifies the other party (push). */
    public function post(Thread $thread, int $senderId, string $body)
    {
        // No files, ever — the third argument stays empty by design.
        return $this->threads->post($thread, $senderId, $body, []);
    }
}
