<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
            'booking_created' => ['حجز جديد', 'New booking', AppNotification::TYPE_BOOKING, AppNotification::PRIORITY_HIGH, true, true, true, true, true, true, 3, 'booking_new'],
            'booking_confirmed' => ['تأكيد حجز', 'Booking confirmed', AppNotification::TYPE_BOOKING, AppNotification::PRIORITY_NORMAL, true, true, true, true, false, false, 0, 'booking_confirmed'],
            'booking_cancelled' => ['إلغاء حجز', 'Booking cancelled', AppNotification::TYPE_BOOKING, AppNotification::PRIORITY_NORMAL, true, true, true, true, false, true, 0, 'booking_cancelled'],
            'delivery_task_assigned' => ['مهمة دليفري جديدة', 'Delivery task assigned', AppNotification::TYPE_SYSTEM, AppNotification::PRIORITY_URGENT, true, true, true, true, true, true, 1, 'delivery_new'],
            'wallet_deposit' => ['إيداع في المحفظة', 'Wallet deposit', AppNotification::TYPE_WALLET, AppNotification::PRIORITY_NORMAL, true, false, false, false, false, false, 0, 'wallet'],
            'wallet_withdraw' => ['خصم من المحفظة', 'Wallet withdraw', AppNotification::TYPE_WALLET, AppNotification::PRIORITY_NORMAL, true, false, true, false, false, false, 0, 'wallet'],
            'guarantee_expiring' => ['قرب انتهاء الضمان', 'Guarantee expiring', AppNotification::TYPE_GUARANTEE, AppNotification::PRIORITY_HIGH, true, false, true, false, false, true, 0, 'warning'],
            'coguarantor_invited' => ['دعوة لمشاركة الضمان', 'Co-guarantor request', AppNotification::TYPE_GUARANTEE, AppNotification::PRIORITY_HIGH, true, false, true, false, false, false, 0, 'guarantee'],
            'coguarantor_accepted' => ['قبول طلب الضمان', 'Co-guarantor accepted', AppNotification::TYPE_GUARANTEE, AppNotification::PRIORITY_NORMAL, true, false, true, false, false, false, 0, 'guarantee'],
            'coguarantor_declined' => ['رفض طلب الضمان', 'Co-guarantor declined', AppNotification::TYPE_GUARANTEE, AppNotification::PRIORITY_NORMAL, true, false, true, false, false, false, 0, 'guarantee'],
            'dispute_opened' => ['نزاع جديد', 'Dispute opened', AppNotification::TYPE_DISPUTE, AppNotification::PRIORITY_URGENT, true, true, true, true, false, true, 5, 'warning'],
            'offer_matched' => ['عرض مناسب لمتابعتك', 'Offer matched your follow', AppNotification::TYPE_OFFER, AppNotification::PRIORITY_NORMAL, true, false, false, false, false, false, 0, 'offer'],
            'shared_cart_member_joined' => ['انضمام عضو للسلة الجماعية', 'Member joined shared cart', AppNotification::TYPE_SYSTEM, AppNotification::PRIORITY_NORMAL, true, true, true, true, false, false, 0, 'system'],
            'system_announcement' => ['تنبيه من النظام', 'System announcement', AppNotification::TYPE_SYSTEM, AppNotification::PRIORITY_NORMAL, true, false, false, false, false, false, 0, 'system'],
        ];
    }

    public static function ensureDefaults(): void
    {
        foreach (self::defaultEventKeys() as $eventKey => $row) {
            self::query()->firstOrCreate(
                ['event_key' => $eventKey],
                [
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
                ]
            );
        }
    }

    public function displayName(): string
    {
        return $this->name_ar ?: ($this->name_en ?: $this->event_key);
    }
}
