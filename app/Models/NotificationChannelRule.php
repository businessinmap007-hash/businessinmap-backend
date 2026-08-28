<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class NotificationChannelRule extends Model
{
    protected $table = 'notification_channel_rules';

    protected $fillable = [
        'event_key',
        'name_ar',
        'name_en',
        'type',
        'priority',
        'is_active',
        'in_app_enabled',
        'realtime_enabled',
        'firebase_enabled',
        'fallback_to_firebase',
        'requires_operator_session',
        'critical',
        'escalation_minutes',
        'sound_key',
        'rules',
        'meta',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'in_app_enabled' => 'boolean',
        'realtime_enabled' => 'boolean',
        'firebase_enabled' => 'boolean',
        'fallback_to_firebase' => 'boolean',
        'requires_operator_session' => 'boolean',
        'critical' => 'boolean',
        'escalation_minutes' => 'integer',
        'rules' => 'array',
        'meta' => 'array',
    ];

    public static function defaultEventKeys(): array
    {
        return [
            'menu_order_created' => ['طلب منيو جديد', 'New menu order', AppNotification::TYPE_OFFER, AppNotification::PRIORITY_HIGH, true, true, true, true, true, true, 2, 'order_new'],
            'menu_order_cancelled' => ['إلغاء طلب منيو', 'Menu order cancelled', AppNotification::TYPE_OFFER, AppNotification::PRIORITY_NORMAL, true, true, true, true, false, true, 0, 'order_cancelled'],
            'menu_order_completed' => ['اكتمال توصيل الطلب', 'Order delivered', AppNotification::TYPE_SYSTEM, AppNotification::PRIORITY_NORMAL, true, true, true, true, false, false, 0, 'order_new'],
            // Prep lifecycle updates to the customer (dispatched by the business
            // accept/preparing/ready transitions). Without these rules the
            // dispatcher drops the alert, so the customer never learns the status.
            'menu_order_accepted' => ['قبول الطلب', 'Order accepted', AppNotification::TYPE_OFFER, AppNotification::PRIORITY_NORMAL, true, true, true, true, false, false, 0, 'order_new'],
            'menu_order_preparing' => ['الطلب قيد التحضير', 'Order being prepared', AppNotification::TYPE_OFFER, AppNotification::PRIORITY_NORMAL, true, true, true, true, false, false, 0, 'order_new'],
            'menu_order_ready' => ['الطلب جاهز', 'Order ready', AppNotification::TYPE_OFFER, AppNotification::PRIORITY_HIGH, true, true, true, true, false, false, 0, 'order_new'],
            // A dine-in customer calls staff / asks for the bill from their table
            // (BIM-13.3). Routed to the business with the table label; escalates so
            // an unattended call is chased.
            'table_service_requested' => ['نداء من طاولة', 'Table service request', AppNotification::TYPE_OFFER, AppNotification::PRIORITY_HIGH, true, true, true, true, true, false, 2, 'order_new'],
            'delivery_assigned' => ['قبول موصّل للطلب', 'Driver accepted delivery', AppNotification::TYPE_SYSTEM, AppNotification::PRIORITY_NORMAL, true, true, true, true, false, false, 0, 'delivery_new'],
            // The three generic booking_* keys above this comment used to live
            // here but were never dispatched by anything — every real booking
            // notification goes through ServiceEventDispatcher/ServiceEventKeys
            // instead, with its own finer-grained `booking.*` keys (2026-08-28
            // audit). Replaced by the real keys below so booking notifications
            // finally get realtime + Firebase like every other live event,
            // rather than silently staying in-app-only forever. See
            // ServiceEventNotificationService::notifyUser().
            'booking.requested' => ['طلب حجز جديد', 'New booking request', AppNotification::TYPE_BOOKING, AppNotification::PRIORITY_HIGH, true, true, true, true, true, true, 3, 'booking_new'],
            'booking.accepted' => ['تم قبول الحجز', 'Booking accepted', AppNotification::TYPE_BOOKING, AppNotification::PRIORITY_HIGH, true, true, true, true, false, false, 0, 'booking_confirmed'],
            'booking.rejected' => ['تم رفض الحجز', 'Booking rejected', AppNotification::TYPE_BOOKING, AppNotification::PRIORITY_HIGH, true, true, true, true, false, true, 0, 'booking_cancelled'],
            'booking.cancelled' => ['إلغاء حجز', 'Booking cancelled', AppNotification::TYPE_BOOKING, AppNotification::PRIORITY_HIGH, true, true, true, true, false, true, 0, 'booking_cancelled'],
            'booking.rescheduled' => ['إعادة جدولة الحجز', 'Booking rescheduled', AppNotification::TYPE_BOOKING, AppNotification::PRIORITY_NORMAL, true, true, true, true, false, false, 0, 'booking_new'],
            'booking.started' => ['بدأ تنفيذ الحجز', 'Booking started', AppNotification::TYPE_BOOKING, AppNotification::PRIORITY_HIGH, true, true, true, true, false, false, 0, 'booking_new'],
            'booking.completed' => ['تم إنهاء الحجز', 'Booking completed', AppNotification::TYPE_BOOKING, AppNotification::PRIORITY_NORMAL, true, true, true, true, false, false, 0, 'booking_confirmed'],
            'booking.client_confirmed' => ['تأكيد العميل', 'Client confirmed the booking', AppNotification::TYPE_BOOKING, AppNotification::PRIORITY_HIGH, true, true, true, true, false, false, 0, 'booking_confirmed'],
            'booking.business_confirmed' => ['تأكيد مقدم الخدمة', 'Provider confirmed the booking', AppNotification::TYPE_BOOKING, AppNotification::PRIORITY_HIGH, true, true, true, true, false, false, 0, 'booking_confirmed'],
            'booking.reminder_24h' => ['تذكير بموعد الحجز', 'Booking reminder (24h)', AppNotification::TYPE_BOOKING, AppNotification::PRIORITY_HIGH, true, true, true, true, false, false, 0, 'booking_reminder'],
            'booking.reminder_1h' => ['تذكير قريب بموعد الحجز', 'Booking reminder (1h)', AppNotification::TYPE_BOOKING, AppNotification::PRIORITY_HIGH, true, true, true, true, false, false, 0, 'booking_reminder'],
            'booking.deposit_frozen' => ['تم تجميد الضمان', 'Booking deposit frozen', AppNotification::TYPE_BOOKING, AppNotification::PRIORITY_HIGH, true, true, true, true, false, false, 0, 'booking_new'],
            'booking.deposit_released' => ['تم تحرير الضمان', 'Booking deposit released', AppNotification::TYPE_BOOKING, AppNotification::PRIORITY_NORMAL, true, true, true, true, false, false, 0, 'booking_confirmed'],
            'booking.deposit_refunded' => ['تم استرداد الضمان', 'Booking deposit refunded', AppNotification::TYPE_BOOKING, AppNotification::PRIORITY_NORMAL, true, true, true, true, false, false, 0, 'booking_confirmed'],
            'booking.dispute_opened' => ['نزاع على حجز', 'Dispute opened on a booking', AppNotification::TYPE_BOOKING, AppNotification::PRIORITY_URGENT, true, true, true, true, false, true, 2, 'warning'],
            'delivery_task_assigned' => ['مهمة دليفري جديدة', 'Delivery task assigned', AppNotification::TYPE_SYSTEM, AppNotification::PRIORITY_URGENT, true, true, true, true, true, true, 1, 'delivery_new'],
            'wallet_deposit' => ['إيداع في المحفظة', 'Wallet deposit', AppNotification::TYPE_WALLET, AppNotification::PRIORITY_NORMAL, true, false, false, false, false, false, 0, 'wallet'],
            'wallet_withdraw' => ['خصم من المحفظة', 'Wallet withdraw', AppNotification::TYPE_WALLET, AppNotification::PRIORITY_NORMAL, true, false, true, false, false, false, 0, 'wallet'],
            'guarantee_expiring' => ['قرب انتهاء الضمان', 'Guarantee expiring', AppNotification::TYPE_GUARANTEE, AppNotification::PRIORITY_HIGH, true, false, true, false, false, true, 0, 'warning'],
            'coguarantor_invited' => ['دعوة لمشاركة الضمان', 'Co-guarantor request', AppNotification::TYPE_GUARANTEE, AppNotification::PRIORITY_HIGH, true, false, true, false, false, false, 0, 'guarantee'],
            'coguarantor_accepted' => ['قبول طلب الضمان', 'Co-guarantor accepted', AppNotification::TYPE_GUARANTEE, AppNotification::PRIORITY_NORMAL, true, false, true, false, false, false, 0, 'guarantee'],
            'coguarantor_declined' => ['رفض طلب الضمان', 'Co-guarantor declined', AppNotification::TYPE_GUARANTEE, AppNotification::PRIORITY_NORMAL, true, false, true, false, false, false, 0, 'guarantee'],
            'dispute_opened' => ['نزاع جديد', 'Dispute opened', AppNotification::TYPE_DISPUTE, AppNotification::PRIORITY_URGENT, true, true, true, true, false, true, 5, 'warning'],
            'dispute_resolved' => ['صدور قرار في نزاع', 'Dispute ruling issued', AppNotification::TYPE_DISPUTE, AppNotification::PRIORITY_HIGH, true, true, true, true, false, true, 0, 'warning'],
            'dispute_fine' => ['غرامة منصة على نزاع', 'Platform fine on a dispute', AppNotification::TYPE_DISPUTE, AppNotification::PRIORITY_HIGH, true, true, true, true, false, true, 0, 'warning'],
            'dispute_room_message' => ['رسالة جديدة في غرفة النزاع', 'New message in the dispute room', AppNotification::TYPE_MESSAGE, AppNotification::PRIORITY_HIGH, true, true, true, true, false, false, 0, 'system'],
            'offer_matched' => ['عرض مناسب لمتابعتك', 'Offer matched your follow', AppNotification::TYPE_OFFER, AppNotification::PRIORITY_NORMAL, true, false, false, false, false, false, 0, 'offer'],
            'job_posted' => ['وظيفة جديدة في مجال تتابعه', 'New job in a field you follow', AppNotification::TYPE_OFFER, AppNotification::PRIORITY_NORMAL, true, false, true, false, false, false, 0, 'offer'],
            'job_application_approved' => ['تم قبول تقديمك على وظيفة', 'Your job application was accepted', AppNotification::TYPE_SYSTEM, AppNotification::PRIORITY_HIGH, true, false, true, false, false, false, 0, 'system'],
            'post_commented' => ['تعليق جديد على منشورك', 'New comment on your post', AppNotification::TYPE_SYSTEM, AppNotification::PRIORITY_NORMAL, true, false, true, false, false, false, 0, 'system'],
            'comment_replied' => ['رد على تعليقك', 'Someone replied to your comment', AppNotification::TYPE_SYSTEM, AppNotification::PRIORITY_NORMAL, true, false, true, false, false, false, 0, 'system'],
            'trip_reservation_created' => ['حجز رحلة جديد', 'New trip reservation', AppNotification::TYPE_BOOKING, AppNotification::PRIORITY_HIGH, true, true, true, true, false, true, 2, 'booking_new'],
            'trip_reservation_confirmed' => ['تأكيد حجز الرحلة', 'Trip reservation confirmed', AppNotification::TYPE_BOOKING, AppNotification::PRIORITY_NORMAL, true, true, true, true, false, false, 0, 'booking_confirmed'],
            'trip_reservation_completed' => ['اكتمال الرحلة', 'Trip completed', AppNotification::TYPE_SYSTEM, AppNotification::PRIORITY_NORMAL, true, true, true, true, false, false, 0, 'booking_confirmed'],
            'trip_reservation_cancelled' => ['إلغاء حجز الرحلة', 'Trip reservation cancelled', AppNotification::TYPE_BOOKING, AppNotification::PRIORITY_NORMAL, true, true, true, true, false, true, 0, 'booking_cancelled'],
            'shared_cart_member_joined' => ['انضمام عضو للسلة الجماعية', 'Member joined shared cart', AppNotification::TYPE_SYSTEM, AppNotification::PRIORITY_NORMAL, true, true, true, true, false, false, 0, 'system'],
            'shared_cart_cancelled' => ['إلغاء السلة الجماعية', 'Shared cart cancelled', AppNotification::TYPE_SYSTEM, AppNotification::PRIORITY_NORMAL, true, true, true, true, false, false, 0, 'system'],
            'system_announcement' => ['تنبيه من النظام', 'System announcement', AppNotification::TYPE_SYSTEM, AppNotification::PRIORITY_NORMAL, true, false, false, false, false, false, 0, 'system'],
            // A new message in any conversation (dispute room, operation chat,
            // direct or group). In-app + realtime, with Firebase push so the
            // other party is reached even with the app closed.
            'chat_message' => ['رسالة جديدة', 'New message', AppNotification::TYPE_MESSAGE, AppNotification::PRIORITY_NORMAL, true, true, true, true, false, false, 0, 'message'],

            // A project stage was completed — the contracted customer and any
            // approved followers are told progress advanced. Reaches them with
            // the app closed (Firebase), so they can check the new milestone.
            'project_stage_completed' => ['اكتمال مرحلة في المشروع', 'A project stage was completed', AppNotification::TYPE_SYSTEM, AppNotification::PRIORITY_NORMAL, true, true, true, true, false, false, 0, 'system'],

            // Medical prescriptions: the doctor issues one (→ patient), the
            // patient sends it to a pharmacy (→ pharmacy), the pharmacy has it
            // ready (→ patient).
            'prescription_issued' => ['وصفة طبية جديدة', 'New prescription', AppNotification::TYPE_SYSTEM, AppNotification::PRIORITY_HIGH, true, true, true, true, false, false, 0, 'system'],
            'prescription_received' => ['وصفة طبية لتجهيزها', 'A prescription to prepare', AppNotification::TYPE_SYSTEM, AppNotification::PRIORITY_HIGH, true, true, true, true, false, true, 0, 'system'],
            'prescription_ready' => ['دواؤك جاهز', 'Your medicine is ready', AppNotification::TYPE_SYSTEM, AppNotification::PRIORITY_HIGH, true, true, true, true, false, false, 0, 'system'],
            // The pharmacy priced the prescription — the patient can see the invoice.
            'prescription_priced' => ['فاتورة دوائك جاهزة', 'Your medicine invoice is ready', AppNotification::TYPE_SYSTEM, AppNotification::PRIORITY_HIGH, true, true, true, true, false, false, 0, 'system'],
            // A driver delivered the prescription — tells the pharmacy (→ pharmacy).
            'prescription_delivered' => ['تم تسليم الوصفة', 'Prescription delivered', AppNotification::TYPE_SYSTEM, AppNotification::PRIORITY_NORMAL, true, true, true, true, false, false, 0, 'system'],

            // Training: a coach assigns/updates a client's plan (→ the client).
            'training_plan_assigned' => ['خطة تدريب جديدة', 'New training plan', AppNotification::TYPE_SYSTEM, AppNotification::PRIORITY_HIGH, true, true, true, true, false, false, 0, 'system'],

            // Clinic appointments: a patient requests one (→ clinic); the clinic
            // confirms it (→ patient).
            'appointment_requested' => ['طلب موعد جديد', 'New appointment request', AppNotification::TYPE_BOOKING, AppNotification::PRIORITY_HIGH, true, true, true, true, false, true, 0, 'booking_new'],
            'appointment_confirmed' => ['تأكيد الموعد', 'Appointment confirmed', AppNotification::TYPE_BOOKING, AppNotification::PRIORITY_HIGH, true, true, true, true, false, false, 0, 'booking_confirmed'],
            // A pre-visit reminder for a confirmed appointment (→ the patient).
            'appointment_reminder' => ['تذكير بالموعد', 'Appointment reminder', AppNotification::TYPE_BOOKING, AppNotification::PRIORITY_NORMAL, true, true, true, true, false, false, 0, 'booking_reminder'],
            // The patient moved their appointment to a new time (→ the clinic).
            'appointment_rescheduled' => ['إعادة جدولة موعد', 'Appointment rescheduled', AppNotification::TYPE_BOOKING, AppNotification::PRIORITY_HIGH, true, true, true, true, false, false, 0, 'booking_new'],
            // A due medication dose from the agenda (→ the patient).
            'medication_reminder' => ['تذكير بالدواء', 'Medication reminder', AppNotification::TYPE_SYSTEM, AppNotification::PRIORITY_HIGH, true, true, true, true, false, false, 0, 'system'],
            // A due personal task from the agenda (→ the owner).
            'agenda_reminder' => ['تذكير بمهمة', 'Task reminder', AppNotification::TYPE_SYSTEM, AppNotification::PRIORITY_NORMAL, true, true, true, true, false, false, 0, 'system'],

            // The shared retail catalog now has stock under a business's
            // allowed types, and it never listed a single product — the
            // screen has worked since 2026-08-04 but nothing ever announced
            // it (2026-08-28 investigation: zero business_catalog_listings
            // despite 1,295 catalog rows). Sent once per business, ever.
            'retail_catalog_ready' => ['بضاعة جاهزة في كتالوج التجزئة', 'Retail catalog has products for you', AppNotification::TYPE_SYSTEM, AppNotification::PRIORITY_NORMAL, true, true, true, true, false, false, 0, 'system'],
        ];
    }

    public static function ensureDefaults(): void
    {
        $defaults = self::defaultEventKeys();

        // The common case, by far: every default row already exists. Skip
        // straight past the 50+ firstOrCreate() calls below.
        if (self::query()->whereIn('event_key', array_keys($defaults))->count() === count($defaults)) {
            return;
        }

        try {
            // MySQL's own advice for SQLSTATE 40001 is "restart the
            // transaction" — this app runs against a live, shared database,
            // so real traffic can be writing to this same table at the same
            // moment. The retry only actually fires when this call is NOT
            // already nested inside a caller's own transaction (Laravel
            // disables mid-savepoint retries — see the try/catch below for
            // that case instead).
            DB::transaction(function () use ($defaults) {
                foreach ($defaults as $eventKey => $row) {
                    self::query()->firstOrCreate(['event_key' => $eventKey], self::attributesFromRow($row));
                }
            }, 3);
        } catch (\Throwable $e) {
            // Best-effort seeding: a deadlock here must never abort a
            // caller's own transaction (a booking, an order accept, …) over
            // a config table. Whatever key the caller actually needed either
            // already existed or will seed itself on a later, luckier call —
            // dispatch() already degrades gracefully when a rule is missing.
            report($e);
        }
    }

    /**
     * The narrow, dispatch()-facing counterpart to ensureDefaults(): seeds
     * only the ONE row a specific event actually needs instead of sweeping
     * all ~50, so a single dispatch() call touches (at most) one row instead
     * of racing real traffic across the whole table every time.
     */
    public static function ensureDefault(string $eventKey): void
    {
        $defaults = self::defaultEventKeys();

        if (! array_key_exists($eventKey, $defaults)) {
            return;
        }

        if (self::query()->where('event_key', $eventKey)->exists()) {
            return;
        }

        try {
            DB::transaction(function () use ($eventKey, $defaults) {
                self::query()->firstOrCreate(['event_key' => $eventKey], self::attributesFromRow($defaults[$eventKey]));
            }, 3);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private static function attributesFromRow(array $row): array
    {
        return [
            'name_ar' => $row[0],
            'name_en' => $row[1],
            'type' => $row[2],
            'priority' => $row[3],
            'is_active' => true,
            'in_app_enabled' => (bool) $row[4],
            'realtime_enabled' => (bool) $row[5],
            'firebase_enabled' => (bool) $row[6],
            'fallback_to_firebase' => (bool) $row[7],
            'requires_operator_session' => (bool) $row[8],
            'critical' => (bool) $row[9],
            'escalation_minutes' => (int) $row[10],
            'sound_key' => $row[11],
        ];
    }

    public function displayName(): string
    {
        return $this->name_ar ?: ($this->name_en ?: $this->event_key);
    }
}
