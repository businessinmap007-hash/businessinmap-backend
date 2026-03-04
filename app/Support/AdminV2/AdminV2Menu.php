<?php

namespace App\Support\AdminV2;

use App\Models\User;

final class AdminV2Menu
{
        public static function visibleFor(User $user): array
        {
            // ✅ (اختياري) عناصر ثابتة قليلة جدًا فقط
            $base = [
                [
                    'type'   => 'link',
                    'label'  => 'الرئيسية',
                    'route'  => 'admin.dashboard',
                    'active' => 'admin.dashboard',
                    'icon'   => 'dashboard',
                ],
            ];

            $sections = AdminV2PermissionMap::sections();
            $filtered = [];

            foreach ($sections as $section) {
                if (!self::canSeeItem($user, $section)) {
                    continue;
                }

                $hasChildren = isset($section['children']) && is_array($section['children']);
                $hasRoute    = !empty($section['route']);

                // ✅ Normalize icon: من zmdi class إلى key بسيط
                $section['icon'] = self::normalizeIconKey($section['key'] ?? null, $section['icon'] ?? null);

                // children filtering + normalize
                if ($hasChildren) {
                    $children = [];

                    foreach ($section['children'] as $child) {
                        if (!self::canSeeItem($user, $child)) {
                            continue;
                        }

                        $child['type'] = $child['type'] ?? 'link';
                        $child['icon'] = self::normalizeIconKey(null, $child['icon'] ?? null);

                        // ✅ active auto
                        if (empty($child['active']) && !empty($child['route']) && is_string($child['route'])) {
                            $child['active'] = self::routeToActivePrefix($child['route']);
                        }

                        $children[] = $child;
                    }

                    // لو مفيش children ومفيش route → اخفاء
                    if (count($children) === 0 && !$hasRoute) {
                        continue;
                    }

                    $section['children'] = $children;
                }

                // ✅ type default
                if (!isset($section['type'])) {
                    $section['type'] = ($hasChildren && !$hasRoute) ? 'section' : 'link';
                }

                // ✅ active auto للـ section/link
                if (empty($section['active']) && !empty($section['route']) && is_string($section['route'])) {
                    $section['active'] = self::routeToActivePrefix($section['route']);
                }

                $filtered[] = $section;
            }

            // ✅ دمج base + filtered
            $merged = array_merge($base, $filtered);

            // ✅ منع التكرار على أساس key أو route
            $unique = [];
            foreach ($merged as $item) {
                $type  = (string)($item['type'] ?? 'link');
                $key   = (string)($item['key'] ?? '');
                $route = (string)($item['route'] ?? '');
                $label = (string)($item['label'] ?? '');

                $uniqKey = $key !== '' ? ($type.'|key|'.$key) : ($route !== '' ? ($type.'|route|'.$route) : ($type.'|label|'.$label));
                $unique[$uniqKey] = $item;
            }

            return array_values($unique);
        }

        private static function routeToActivePrefix(string $route): string
        {
            // admin.posts.index => admin.posts.
            if (str_ends_with($route, '.index')) {
                return rtrim(substr($route, 0, -strlen('index')), '.') . '.';
            }

            // admin.coupons.create => admin.coupons.
            $parts = explode('.', $route);
            if (count($parts) >= 2) {
                array_pop($parts);
                return implode('.', $parts) . '.';
            }

            return $route;
        }

        private static function normalizeIconKey($key, $icon): string
        {
            $key = (string)($key ?? '');
            $icon = (string)($icon ?? '');

            // أفضل اعتماد على key إن موجود
            if ($key !== '') {
                return match ($key) {
                    'dashboard'     => 'dashboard',
                    'system'        => 'shield',
                    'users'         => 'users',
                    'categories'    => 'folder',
                    'posts'         => 'file',
                    'jobs'          => 'briefcase',
                    'sponsors'      => 'megaphone',
                    'transactions'  => 'credit',
                    'albums'        => 'image',
                    'coupons'       => 'ticket',
                    'settings'      => 'settings',
                    default         => 'dot',
                };
            }

            // fallback لو icon كان zmdi
            if (str_contains($icon, 'dashboard')) return 'dashboard';
            if (str_contains($icon, 'accounts'))  return 'users';
            if (str_contains($icon, 'layers'))    return 'shield';

            return 'dot';
        }


    private static function canSeeItem(User $user, array $item): bool
    {
        $isOwner     = method_exists($user, 'isAn') ? (bool) $user->isAn('owner') : false;
        $isAdminType = (($user->type ?? null) === 'admin');

        // ✅ لو Owner/AdminType: شوف كل حاجة
        if ($isOwner || $isAdminType) {
            return true;
        }

        // owner_only: ممنوع لغير owner/admin
        if (!empty($item['owner_only'])) {
            return false;
        }

        $ability = $item['ability'] ?? null;
        if (!$ability) {
            return true;
        }

        return $user->can($ability);
    }
}
