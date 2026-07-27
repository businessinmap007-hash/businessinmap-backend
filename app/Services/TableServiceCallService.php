<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\BusinessTable;
use App\Models\TableServiceCall;
use App\Services\Notifications\NotificationDispatcherService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Dine-in table service calls (BIM-13.3): a customer at a table asks staff to
 * come over or bring the bill. Anchored on the table's permanent QR token — the
 * scan is physical proof of presence — and delivered to the business with the
 * table label so staff know where to go. See CustomerCartService for the sibling
 * table-cart flow and NotificationChannelRule `table_service_requested`.
 */
class TableServiceCallService
{
    public function __construct(protected NotificationDispatcherService $notifications)
    {
    }

    /**
     * Raise a service call for a table. Idempotent per (table, type): a live
     * pending call of the same type is reused (and staff is NOT re-notified) so a
     * customer tapping twice can't flood the kitchen. The table row is locked to
     * serialise concurrent scanners, mirroring joinOrCreateForTable.
     */
    public function call(int $userId, BusinessTable $table, string $type, ?string $note = null): TableServiceCall
    {
        $type = in_array($type, TableServiceCall::TYPES, true) ? $type : TableServiceCall::TYPE_WAITER;

        [$call, $isNew] = DB::transaction(function () use ($userId, $table, $type, $note) {
            BusinessTable::query()->whereKey($table->id)->lockForUpdate()->first();

            $existing = TableServiceCall::query()
                ->where('business_table_id', $table->id)
                ->where('type', $type)
                ->where('status', TableServiceCall::STATUS_PENDING)
                ->first();

            if ($existing) {
                return [$existing, false];
            }

            $call = TableServiceCall::create([
                'business_id' => (int) $table->business_id,
                'business_table_id' => (int) $table->id,
                'user_id' => $userId,
                'type' => $type,
                'status' => TableServiceCall::STATUS_PENDING,
                'note' => $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 300) : null,
            ]);

            return [$call, true];
        });

        if ($isNew) {
            $this->notifyBusiness($call, $table);
        }

        return $call;
    }

    /** Business marks a call handled. Scoped to the business; idempotent. */
    public function resolve(int $businessId, int $callId, int $resolverId): TableServiceCall
    {
        /** @var TableServiceCall $call */
        $call = TableServiceCall::query()
            ->where('business_id', $businessId)
            ->findOrFail($callId);

        if ($call->status !== TableServiceCall::STATUS_RESOLVED) {
            $call->status = TableServiceCall::STATUS_RESOLVED;
            $call->resolved_by = $resolverId;
            $call->resolved_at = now();
            $call->save();
        }

        return $call;
    }

    /** The business's open (pending) calls, newest first, with table labels. */
    public function pendingFor(int $businessId): Collection
    {
        return TableServiceCall::query()
            ->where('business_id', $businessId)
            ->where('status', TableServiceCall::STATUS_PENDING)
            ->with('table:id,label')
            ->latest('id')
            ->get();
    }

    /** Best-effort alert to the business, carrying the table label. */
    private function notifyBusiness(TableServiceCall $call, BusinessTable $table): void
    {
        $businessId = (int) $table->business_id;
        if ($businessId <= 0) {
            return;
        }

        try {
            $label = trim((string) $table->label);

            $this->notifications->dispatch('table_service_requested', $businessId, [
                'type' => AppNotification::TYPE_OFFER,
                'actor_id' => (int) $call->user_id,
                'body_ar' => $call->labelAr() . ' من طاولة ' . $label . '.',
                'body_en' => $call->labelEn() . ' from table ' . $label . '.',
                'action_type' => 'open_table_calls',
                'action_url' => '/business/table-calls',
                'notifiable_type' => TableServiceCall::class,
                'notifiable_id' => (int) $call->id,
                'source_id' => (int) $call->id,
                'meta' => [
                    'call_id' => (int) $call->id,
                    'business_table_id' => (int) $table->id,
                    'table_label' => $label,
                    'type' => $call->type,
                ],
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
