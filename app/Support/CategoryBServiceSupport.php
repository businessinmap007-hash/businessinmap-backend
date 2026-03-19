<?php

namespace App\Support\AdminV2;

use App\Models\Category;
use App\Models\CategoryServiceConfig;
use App\Models\PlatformService;

class CategoryServiceSupport
{
    public static function getActiveBookingConfig(?Category $category): ?CategoryServiceConfig
    {
        if (! $category) {
            return null;
        }

        $service = PlatformService::query()
            ->where('key', 'booking')
            ->first(['id']);

        if (! $service) {
            return null;
        }

        if ($category->relationLoaded('serviceConfigs')) {
            return $category->serviceConfigs
                ->first(function ($config) use ($service) {
                    return (int) $config->platform_service_id === (int) $service->id
                        && (bool) $config->is_active === true;
                });
        }

        return $category->serviceConfigs()
            ->where('platform_service_id', $service->id)
            ->where('is_active', true)
            ->first();
    }

    public static function supportsBooking(?Category $category): bool
    {
        if (! $category) {
            return false;
        }

        return $category->hasService('booking');
    }

    public static function allowsItemType(?Category $category, ?string $itemType): bool
    {
        if (! $category || ! $itemType) {
            return false;
        }

        $configRow = self::getActiveBookingConfig($category);

        if (! $configRow) {
            return false;
        }

        $config = is_array($configRow->config)
            ? $configRow->config
            : [];

        $allowedItemTypes = $config['allowed_item_types'] ?? [];

        if (! is_array($allowedItemTypes)) {
            return false;
        }

        $allowedItemTypes = collect($allowedItemTypes)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($allowedItemTypes)) {
            return true;
        }

        return in_array($itemType, $allowedItemTypes, true);
    }

    public static function bookingModes(?Category $category): array
    {
        $configRow = self::getActiveBookingConfig($category);

        if (! $configRow) {
            return [];
        }

        $config = is_array($configRow->config)
            ? $configRow->config
            : [];

        $modes = $config['booking_modes'] ?? [];

        if (! is_array($modes)) {
            return [];
        }

        return collect($modes)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public static function requiredFields(?Category $category): array
    {
        $configRow = self::getActiveBookingConfig($category);

        if (! $configRow) {
            return [];
        }

        $config = is_array($configRow->config)
            ? $configRow->config
            : [];

        $fields = $config['required_fields'] ?? [];

        if (! is_array($fields)) {
            return [];
        }

        return collect($fields)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public static function itemFamily(?Category $category): ?string
    {
        $configRow = self::getActiveBookingConfig($category);

        if (! $configRow) {
            return null;
        }

        $config = is_array($configRow->config)
            ? $configRow->config
            : [];

        $value = trim((string) ($config['item_family'] ?? ''));

        return $value !== '' ? $value : null;
    }
}