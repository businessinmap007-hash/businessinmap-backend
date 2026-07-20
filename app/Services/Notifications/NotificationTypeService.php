<?php

namespace App\Services\Notifications;

use App\Models\AppNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class NotificationTypeService
{
    public function baseTypes(): array
    {
        return AppNotification::types();
    }

    public function enabledPlatformServiceTypes(): array
    {
        if (! Schema::hasTable('platform_services')) {
            return [];
        }

        $query = DB::table('platform_services')
            ->where('is_active', 1);

        $columns = ['id', 'key', 'name_ar', 'name_en'];

        if (Schema::hasColumn('platform_services', 'rules')) {
            $columns[] = 'rules';
        }

        if (Schema::hasColumn('platform_services', 'meta')) {
            $columns[] = 'meta';
        }

        return $query
            ->orderBy('name_ar')
            ->get($columns)
            ->filter(fn ($service) => $this->notificationEnabled($service))
            ->mapWithKeys(function ($service) {
                $key = trim((string) $service->key);

                return [$key => [
                    'key' => $key,
                    'label_ar' => $service->name_ar ?: $key,
                    'label_en' => $service->name_en ?: $key,
                    'source' => 'platform_service',
                    'platform_service_id' => (int) $service->id,
                ]];
            })
            ->filter(fn ($item, $key) => $key !== '')
            ->toArray();
    }

    public function allTypes(): array
    {
        return array_values(array_unique(array_merge(
            $this->baseTypes(),
            array_keys($this->enabledPlatformServiceTypes())
        )));
    }

    public function options(): array
    {
        $options = [];

        foreach ($this->baseTypes() as $type) {
            $options[$type] = [
                'key' => $type,
                'label_ar' => $this->baseLabelAr($type),
                'label_en' => ucfirst(str_replace('_', ' ', $type)),
                'source' => 'core',
                'platform_service_id' => null,
            ];
        }

        foreach ($this->enabledPlatformServiceTypes() as $key => $item) {
            if (! isset($options[$key])) {
                $options[$key] = $item;
            }
        }

        return $options;
    }

    public function isAllowed(string $type): bool
    {
        return in_array($type, $this->allTypes(), true);
    }

    public function typeForServiceKey(?string $serviceKey): string
    {
        $serviceKey = trim((string) $serviceKey);

        if ($serviceKey !== '' && $this->isAllowed($serviceKey)) {
            return $serviceKey;
        }

        return match ($serviceKey) {
            'booking' => AppNotification::TYPE_BOOKING,
            default => AppNotification::TYPE_SYSTEM,
        };
    }

    private function notificationEnabled(object $service): bool
    {
        $rules = $this->decodeJsonProperty($service, 'rules');
        $meta = $this->decodeJsonProperty($service, 'meta');

        if (array_key_exists('notification_enabled', $rules)) {
            return filter_var($rules['notification_enabled'], FILTER_VALIDATE_BOOLEAN);
        }

        if (array_key_exists('notification_enabled', $meta)) {
            return filter_var($meta['notification_enabled'], FILTER_VALIDATE_BOOLEAN);
        }

        if (isset($rules['notifications']) && is_array($rules['notifications']) && array_key_exists('enabled', $rules['notifications'])) {
            return filter_var($rules['notifications']['enabled'], FILTER_VALIDATE_BOOLEAN);
        }

        if (isset($meta['notifications']) && is_array($meta['notifications']) && array_key_exists('enabled', $meta['notifications'])) {
            return filter_var($meta['notifications']['enabled'], FILTER_VALIDATE_BOOLEAN);
        }

        return false;
    }

    private function decodeJsonProperty(object $row, string $property): array
    {
        if (! property_exists($row, $property) || ! $row->{$property}) {
            return [];
        }

        if (is_array($row->{$property})) {
            return $row->{$property};
        }

        $decoded = json_decode((string) $row->{$property}, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function baseLabelAr(string $type): string
    {
        return match ($type) {
            AppNotification::TYPE_OFFER => 'العروض',
            AppNotification::TYPE_BOOKING => 'الحجوزات',
            AppNotification::TYPE_WALLET => 'المحفظة',
            AppNotification::TYPE_GUARANTEE => 'الضمان',
            AppNotification::TYPE_DISPUTE => 'النزاعات',
            AppNotification::TYPE_MESSAGE => 'الرسائل',
            AppNotification::TYPE_SYSTEM => 'النظام',
            default => $type,
        };
    }
}
