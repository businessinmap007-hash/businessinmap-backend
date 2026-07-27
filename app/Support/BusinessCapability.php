<?php

namespace App\Support;

/**
 * The single, canonical list of the manageable services on a business account —
 * one place gathering everything a business can operate, and the vocabulary a
 * business grants to a delegated staff member (see [[business_staff]]).
 *
 * Keys are stable strings stored in business_staff.capabilities and named on the
 * `business.member:{capability}` route middleware. Labels are for the picker UI.
 */
final class BusinessCapability
{
    public const ORDERS = 'orders';
    public const MENU = 'menu';
    public const BOOKINGS = 'bookings';
    public const OFFERS = 'offers';
    public const RETAIL = 'retail';
    public const WORKING_HOURS = 'working_hours';
    public const PROJECTS = 'projects';
    public const PRESCRIPTIONS = 'prescriptions';
    public const SCHEDULES = 'schedules';
    public const PRICES = 'prices';
    public const TRAINING = 'training';

    /**
     * The registry: key => [ar, en]. Order here is the display order.
     *
     * @return array<string,array{0:string,1:string}>
     */
    public static function registry(): array
    {
        return [
            self::ORDERS => ['الطلبات', 'Orders'],
            self::MENU => ['المنيو', 'Menu'],
            self::BOOKINGS => ['الحجوزات', 'Bookings'],
            self::OFFERS => ['العروض', 'Offers'],
            self::RETAIL => ['منتجات التجزئة', 'Retail products'],
            self::PRICES => ['الأسعار', 'Prices'],
            self::WORKING_HOURS => ['مواعيد العمل', 'Working hours'],
            self::PROJECTS => ['المشاريع', 'Projects'],
            self::PRESCRIPTIONS => ['الوصفات الطبية', 'Prescriptions'],
            self::SCHEDULES => ['خطوط التشغيل', 'Trip schedules'],
            self::TRAINING => ['خطط التدريب والتغذية', 'Training & nutrition plans'],
        ];
    }

    /** @return list<string> every valid capability key */
    public static function keys(): array
    {
        return array_keys(self::registry());
    }

    public static function isValid(string $key): bool
    {
        return array_key_exists($key, self::registry());
    }

    /** Keep only known keys, de-duplicated and re-indexed. */
    public static function sanitize(array $keys): array
    {
        return array_values(array_unique(array_filter(
            array_map('strval', $keys),
            fn (string $k) => self::isValid($k),
        )));
    }

    /** The registry shaped for an API response. */
    public static function catalog(): array
    {
        $out = [];
        foreach (self::registry() as $key => [$ar, $en]) {
            $out[] = ['key' => $key, 'name_ar' => $ar, 'name_en' => $en];
        }

        return $out;
    }
}
