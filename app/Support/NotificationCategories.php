<?php

namespace App\Support;

use App\Models\NotificationPreference;

/**
 * «اعدادات الاشعارات» — what a user can switch, in the words they know. Every event key belongs to ONE category; a
 * category switched OFF is SILENT: its notifications still arrive in the inbox but raise no push and no sound.
 * A critical rule (money, security) and the platform's own announcements are never silenced.
 */
final class NotificationCategories
{
    /** key => [Arabic label, English label, event-key prefixes] — the order is the screen's order. */
    public const ALL = [
        'orders' => ['الطلبات والتوصيل', 'Orders & delivery', ['menu_order_', 'menu_item_', 'table_service_', 'delivery_', 'shipping_', 'shared_cart_', 'retail_']],
        'bookings' => ['الحجوزات والمواعيد', 'Bookings & appointments', ['booking_', 'booking.', 'appointment_']],
        'reminders' => ['التذكيرات', 'Reminders', ['agenda_reminder', 'medication_reminder']],
        'messages' => ['الرسائل والمحادثات', 'Messages & chats', ['chat_', 'dispute_room_message']],
        'social' => ['المنشورات والعروض والوظائف', 'Posts, offers & jobs', ['post_', 'comment_', 'offer_', 'job_']],
        'trips' => ['الرحلات والمشاريع', 'Trips & projects', ['trip_', 'project_']],
        'money' => ['المحفظة والضمانات والنزاعات', 'Wallet, guarantees & disputes', ['wallet_', 'guarantee_', 'coguarantor_', 'dispute_']],
        'health' => ['الأدوية والوصفات', 'Medicine & prescriptions', ['prescription_']],
        'training' => ['التدريب والخطط', 'Training & plans', ['training_']],
        'team' => ['الموظفون', 'Staff', ['staff_']],
    ];

    /** Never silenced: the platform speaking to you. */
    public const LOCKED = ['system_announcement'];

    /** The category an event key belongs to, or null (not switchable). */
    public static function of(string $eventKey): ?string
    {
        if (in_array($eventKey, self::LOCKED, true)) {
            return null;
        }

        foreach (self::ALL as $key => [, , $prefixes]) {
            foreach ($prefixes as $prefix) {
                if ($eventKey === $prefix || str_starts_with($eventKey, $prefix)) {
                    return $key;
                }
            }
        }

        return null;
    }

    /** @return array<string,bool> category => active (every category; active unless switched off) */
    public static function stateFor(int $userId): array
    {
        $off = NotificationPreference::query()->where('user_id', $userId)->where('enabled', false)->pluck('category')->all();

        return collect(array_keys(self::ALL))->mapWithKeys(fn ($key) => [$key => ! in_array($key, $off, true)])->all();
    }

    /** Is this user's notification of this event key silenced? A critical one never is. */
    public static function isSilenced(int $userId, string $eventKey, bool $critical = false): bool
    {
        if ($critical) {
            return false;
        }

        $category = self::of($eventKey);

        return $category !== null && ! (self::stateFor($userId)[$category] ?? true);
    }
}
